// EduCrm ISUP 5.0 gateway.
//
// Hikvision terminals connect here (Platform Access -> ISUP, port 7660). The gateway:
//  - asks the CRM for each device's ISUP key (unknown devices are refused),
//  - forwards access events (face recognised) to the CRM,
//  - reports devices going online / offline,
//  - lets the CRM send ISAPI commands to a device (add student, face, delete, read events)
//    through a local REST API (127.0.0.1 only, token required).
//
// Configuration comes from the environment (see educrm-isup.env.example).

#include <chrono>
#include <cstdlib>
#include <cstring>
#include <arpa/inet.h>
#include <netdb.h>
#include <iostream>
#include <map>
#include <mutex>
#include <random>
#include <string>
#include <thread>
#include <vector>

#include "HCISUPAlarm.h"
#include "HCISUPCMS.h"
#include "httplib.h"
#include "json.hpp"

using json = nlohmann::json;

struct Config {
    std::string crmHost;       // IP the CRM's web server listens on
    int crmPort;               // usually 80
    std::string crmHostname;   // Host header (crm.mensalc.uz)
    std::string publicIp;      // address devices use to reach this server
    int cmsPort;               // ISUP registration port (7660)
    int alarmPort;             // ISUP alarm port (7662)
    std::string apiHost;       // local REST API bind address
    int apiPort;               // local REST API port (7671)
    std::string token;         // shared secret with the CRM
};

static Config g_cfg;

struct ConnectedDevice {
    LONG loginId;
    std::string deviceId;
    std::string ip;
    std::string serial;
    std::string protocol;
    bool online;
    std::string lastSeen;
};

static std::map<std::string, ConnectedDevice> g_devices;
static std::map<LONG, std::string> g_loginToDevice;
static std::map<std::string, std::string> g_serialToDevice;
static std::mutex g_mutex;

static std::string env(const char* name, const std::string& fallback) {
    const char* value = std::getenv(name);
    return value && *value ? std::string(value) : fallback;
}

static std::string now() {
    auto t = std::chrono::system_clock::to_time_t(std::chrono::system_clock::now());
    char buf[32];
    std::strftime(buf, sizeof(buf), "%Y-%m-%d %H:%M:%S", std::localtime(&t));
    return buf;
}

static std::string randomKey() {
    static const char chars[] = "ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789";
    std::random_device rd;
    std::string key;
    for (int i = 0; i < 16; ++i) key += chars[rd() % (sizeof(chars) - 1)];
    return key;
}

/** IPv4 address of a host name (or the address itself); devices need a numeric alarm server address. */
static std::string resolveIp(const std::string& host) {
    in_addr probe{};
    if (inet_pton(AF_INET, host.c_str(), &probe) == 1) return host;

    addrinfo hints{};
    hints.ai_family = AF_INET;
    addrinfo* result = nullptr;
    if (getaddrinfo(host.c_str(), nullptr, &hints, &result) != 0 || !result) return "";
    char buf[INET_ADDRSTRLEN] = {0};
    inet_ntop(AF_INET, &((sockaddr_in*)result->ai_addr)->sin_addr, buf, sizeof(buf));
    freeaddrinfo(result);
    return buf;
}

static std::string urlEncode(const std::string& value) {
    static const char hex[] = "0123456789ABCDEF";
    std::string out;
    for (unsigned char c : value) {
        if (isalnum(c) || c == '-' || c == '_' || c == '.') {
            out += (char)c;
        } else {
            out += '%';
            out += hex[c >> 4];
            out += hex[c & 15];
        }
    }
    return out;
}

static httplib::Client crmClient(int timeoutSec) {
    httplib::Client cli(g_cfg.crmHost, g_cfg.crmPort);
    cli.set_connection_timeout(std::chrono::seconds(timeoutSec));
    cli.set_read_timeout(std::chrono::seconds(timeoutSec));
    return cli;
}

static httplib::Headers crmHeaders() {
    return {{"Host", g_cfg.crmHostname}, {"X-Gateway-Token", g_cfg.token}, {"Accept", "application/json"}};
}

static void postToCrm(const std::string& path, const json& payload, int timeoutSec) {
    std::thread([path, payload, timeoutSec]() {
        for (int attempt = 0; attempt < 3; ++attempt) {
            try {
                auto cli = crmClient(timeoutSec);
                auto res = cli.Post(path.c_str(), crmHeaders(), payload.dump(), "application/json");
                if (res && res->status < 500) return;
            } catch (...) {
            }
            std::this_thread::sleep_for(std::chrono::seconds(2 * (attempt + 1)));
        }
        std::cerr << "[CRM] could not deliver " << path << std::endl;
    }).detach();
}

/** The device's ISUP key from the CRM; a random one (so the login fails) when the device is unknown. */
static std::string deviceKey(const std::string& deviceId) {
    try {
        auto cli = crmClient(3);
        auto res = cli.Get(("/api/isup/device-key?device_id=" + urlEncode(deviceId)).c_str(), crmHeaders());
        if (res && res->status == 200) {
            auto j = json::parse(res->body);
            if (j.contains("key") && j["key"].is_string()) return j["key"];
        }
    } catch (...) {
    }
    std::cout << "[ISUP AUTH] unknown device refused: " << deviceId << std::endl;
    return randomKey();
}

static BOOL CALLBACK onAlarm(LONG, NET_EHOME_ALARM_MSG* msg, void*) {
    if (!msg || !msg->pXmlBuf || msg->dwXmlBufLen == 0) return TRUE;

    std::string serial = msg->sSerialNumber;
    std::string body((char*)msg->pXmlBuf, msg->dwXmlBufLen);
    std::string deviceId;
    {
        std::lock_guard<std::mutex> lock(g_mutex);
        auto it = g_serialToDevice.find(serial);
        if (it != g_serialToDevice.end()) deviceId = it->second;
    }

    json payload;
    try {
        payload = json::parse(body);
    } catch (...) {
        payload = {{"raw", body}};
    }
    if (payload.value("eventType", "") == "heartBeat") return TRUE;

    payload["gatewayDeviceId"] = deviceId;
    payload["gatewaySerial"] = serial;
    payload["gatewayAlarmType"] = msg->dwAlarmType;
    std::cout << "[ISUP ALARM] device=" << deviceId << " serial=" << serial << " type=" << msg->dwAlarmType << std::endl;
    postToCrm("/api/isup/events", payload, 5);

    return TRUE;
}

static BOOL CALLBACK onRegister(LONG loginId, DWORD type, void* out, DWORD, void* in, DWORD inLen, void*) {
    if (type == ENUM_DEV_AUTH) {
        auto* info = (NET_EHOME_DEV_REG_INFO_V12*)out;
        if (info && in && inLen > 0) {
            std::string key = deviceKey((char*)info->struRegInfo.byDeviceID);
            strncpy((char*)in, key.c_str(), inLen - 1);
            ((char*)in)[inLen - 1] = '\0';
        }
    } else if (type == ENUM_DEV_SESSIONKEY) {
        auto* info = (NET_EHOME_DEV_REG_INFO_V12*)out;
        if (info) {
            NET_EHOME_DEV_SESSIONKEY sessionKey = {0};
            memcpy(sessionKey.sDeviceID, info->struRegInfo.byDeviceID, MAX_DEVICE_ID_LEN);
            memcpy(sessionKey.sSessionKey, info->struRegInfo.bySessionKey, MAX_MASTER_KEY_LEN);
            if (!NET_ECMS_SetDeviceSessionKey(&sessionKey)) {
                std::cerr << "[ISUP] SetDeviceSessionKey error " << NET_ECMS_GetLastError() << std::endl;
            }
        }
    } else if (type == ENUM_DEV_DAS_REQ) {
        json das = {{"Type", "DAS"}, {"DasInfo", {{"Address", g_cfg.publicIp}, {"Domain", ""}, {"ServerID", ""}, {"Port", g_cfg.cmsPort}, {"UdpPort", 0}}}};
        std::string text = das.dump();
        if (in && inLen > 0) {
            strncpy((char*)in, text.c_str(), inLen - 1);
            ((char*)in)[inLen - 1] = '\0';
        }
    } else if (type == ENUM_DEV_ON) {
        auto* info = (NET_EHOME_DEV_REG_INFO*)out;
        if (info) {
            ConnectedDevice dev{loginId, (char*)info->byDeviceID, info->struDevAdd.szIP, (char*)info->sDeviceSerial, (char*)info->byDevProtocolVersion, true, now()};
            {
                std::lock_guard<std::mutex> lock(g_mutex);
                g_devices[dev.deviceId] = dev;
                g_loginToDevice[loginId] = dev.deviceId;
                g_serialToDevice[dev.serial] = dev.deviceId;
            }
            std::cout << "[ISUP ONLINE] " << dev.deviceId << " ip=" << dev.ip << " serial=" << dev.serial << std::endl;
            postToCrm("/api/isup/device-status", {{"device_id", dev.deviceId}, {"status", "online"}, {"ip", dev.ip}, {"serial", dev.serial}}, 3);
        }
        auto* server = (NET_EHOME_SERVER_INFO*)in;
        if (server) {
            server->dwTimeOutCount = 6;
            server->dwKeepAliveSec = 15;
            strncpy(server->struTCPAlarmSever.szIP, g_cfg.publicIp.c_str(), sizeof(server->struTCPAlarmSever.szIP) - 1);
            server->struTCPAlarmSever.wPort = (WORD)g_cfg.alarmPort;
            server->dwAlarmServerType = 1;
        }
    } else if (type == ENUM_DEV_OFF) {
        std::string deviceId;
        {
            std::lock_guard<std::mutex> lock(g_mutex);
            auto it = g_loginToDevice.find(loginId);
            if (it != g_loginToDevice.end()) {
                deviceId = it->second;
                g_devices[deviceId].online = false;
                g_loginToDevice.erase(it);
            }
        }
        NET_ECMS_ForceLogout(loginId);
        if (!deviceId.empty()) {
            std::cout << "[ISUP OFFLINE] " << deviceId << std::endl;
            postToCrm("/api/isup/device-status", {{"device_id", deviceId}, {"status", "offline"}}, 3);
        }
    }
    return TRUE;
}

static bool authorized(const httplib::Request& req) {
    return !g_cfg.token.empty() && req.get_header_value("X-Gateway-Token") == g_cfg.token;
}

int main() {
    g_cfg = Config{
        env("CRM_HOST", "127.0.0.1"),
        std::stoi(env("CRM_PORT", "80")),
        env("CRM_HOSTNAME", "localhost"),
        env("PUBLIC_ADDRESS", env("PUBLIC_IP", "127.0.0.1")),
        std::stoi(env("CMS_PORT", "7660")),
        std::stoi(env("ALARM_PORT", "7662")),
        env("API_HOST", "127.0.0.1"),
        std::stoi(env("API_PORT", "7671")),
        env("GATEWAY_TOKEN", ""),
    };
    // PUBLIC_ADDRESS is the CRM domain; it points at whatever server runs the CRM now.
    std::string publicIp = resolveIp(g_cfg.publicIp);
    if (publicIp.empty()) {
        std::cerr << "cannot resolve PUBLIC_ADDRESS " << g_cfg.publicIp << std::endl;
        return 1;
    }
    std::cout << "Public address " << g_cfg.publicIp << " = " << publicIp << std::endl;
    g_cfg.publicIp = publicIp;

    if (g_cfg.token.size() < 24) {
        std::cerr << "GATEWAY_TOKEN must be set (24+ characters)" << std::endl;
        return 1;
    }

    if (!NET_ECMS_Init()) {
        std::cerr << "NET_ECMS_Init failed: " << NET_ECMS_GetLastError() << std::endl;
        return 1;
    }

    BOOL sessionKeyMode = TRUE;
    NET_ECMS_SetSDKLocalCfg(SESSIONKEY_REQ_MOD, &sessionKeyMode);
    NET_EHOME_LOCAL_ACCESS_SECURITY security = {0};
    security.dwSize = sizeof(security);
    security.byAccessSecurity = 0;
    NET_ECMS_SetSDKLocalCfg(ACTIVE_ACCESS_SECURITY, &security);
    NET_EHOME_SET_REREGISTER_MODE reRegister = {0};
    reRegister.dwSize = sizeof(reRegister);
    reRegister.dwReRegisterMode = 0;
    NET_ECMS_SetSDKLocalCfg(SET_REREGISTER_MODE, &reRegister);
    NET_EHOME_SEND_PARAM sendParam = {0};
    sendParam.dwSize = sizeof(sendParam);
    sendParam.bySendTimes = 3;
    NET_ECMS_SetSDKLocalCfg(SEND_PARAM, &sendParam);
    NET_EHOME_REGISTER_LISTEN_MODE listenMode = {0};
    listenMode.dwSize = sizeof(listenMode);
    listenMode.dwRegisterListenMode = REGISTER_LISTEN_MODE_ALL;
    NET_ECMS_SetSDKLocalCfg(REGISTER_LISTEN_MODE, &listenMode);

    NET_EHOME_CMS_LISTEN_PARAM cms = {0};
    strcpy(cms.struAddress.szIP, "0.0.0.0");
    cms.struAddress.wPort = (WORD)g_cfg.cmsPort;
    cms.fnCB = onRegister;
    cms.dwKeepAliveSec = 15;
    cms.dwTimeOutCount = 6;
    LONG cmsHandle = NET_ECMS_StartListen(&cms);
    if (cmsHandle < 0) {
        std::cerr << "ISUP listen on " << g_cfg.cmsPort << " failed: " << NET_ECMS_GetLastError() << std::endl;
        NET_ECMS_Fini();
        return 1;
    }
    std::cout << "ISUP CMS listening on 0.0.0.0:" << g_cfg.cmsPort << std::endl;

    LONG alarmHandle = -1;
    if (NET_EALARM_Init()) {
        NET_EHOME_ALARM_LISTEN_PARAM alarm = {0};
        strcpy(alarm.struAddress.szIP, "0.0.0.0");
        alarm.struAddress.wPort = (WORD)g_cfg.alarmPort;
        alarm.fnMsgCb = onAlarm;
        alarm.byProtocolType = 0;
        alarm.byUseCmsPort = 0;
        alarm.byUseThreadPool = 1;
        alarm.dwKeepAliveSec = 15;
        alarm.dwTimeOutCount = 6;
        alarmHandle = NET_EALARM_StartListen(&alarm);
        std::cout << (alarmHandle >= 0 ? "ISUP alarms listening on 0.0.0.0:" : "ISUP alarm listen failed on ") << g_cfg.alarmPort << std::endl;
    }

    httplib::Server api;

    api.Get("/health", [](const httplib::Request&, httplib::Response& res) {
        std::lock_guard<std::mutex> lock(g_mutex);
        res.set_content(json{{"status", "ok"}, {"online", g_loginToDevice.size()}}.dump(), "application/json");
    });

    api.Get("/api/devices", [](const httplib::Request& req, httplib::Response& res) {
        if (!authorized(req)) {
            res.status = 401;
            return;
        }
        json list = json::array();
        std::lock_guard<std::mutex> lock(g_mutex);
        for (const auto& [id, d] : g_devices) {
            list.push_back({{"device_id", d.deviceId}, {"ip", d.ip}, {"serial", d.serial}, {"protocol", d.protocol}, {"online", d.online}, {"last_seen", d.lastSeen}});
        }
        res.set_content(list.dump(), "application/json");
    });

    // {"device_id": "...", "method": "PUT", "url": "/ISAPI/...", "body": "..."}
    api.Post("/api/isapi", [](const httplib::Request& req, httplib::Response& res) {
        if (!authorized(req)) {
            res.status = 401;
            return;
        }
        try {
            auto j = json::parse(req.body);
            std::string deviceId = j.value("device_id", "");
            std::string method = j.value("method", "GET");
            std::string url = method + " " + j.value("url", "");
            std::string body = j.value("body", "");

            LONG loginId = -1;
            {
                std::lock_guard<std::mutex> lock(g_mutex);
                auto it = g_devices.find(deviceId);
                if (it != g_devices.end() && it->second.online) loginId = it->second.loginId;
            }
            if (loginId < 0) {
                res.status = 404;
                res.set_content(R"({"success":false,"error":"offline"})", "application/json");
                return;
            }

            std::vector<char> out(1024 * 1024, 0);
            NET_EHOME_PTXML_PARAM param = {0};
            param.pRequestUrl = (void*)url.c_str();
            param.dwRequestUrlLen = (DWORD)url.size();
            if (!body.empty()) {
                param.pInBuffer = (void*)body.c_str();
                param.dwInSize = (DWORD)body.size();
            }
            param.pOutBuffer = out.data();
            param.dwOutSize = (DWORD)out.size();
            param.dwRecvTimeOut = (DWORD)j.value("timeout_ms", 15000);

            BOOL ok = method == "GET" ? NET_ECMS_GetPTXMLConfig(loginId, &param)
                    : method == "PUT" ? NET_ECMS_PutPTXMLConfig(loginId, &param)
                    : method == "DELETE" ? NET_ECMS_DeletePTXMLConfig(loginId, &param)
                    : NET_ECMS_PostPTXMLConfig(loginId, &param);
            if (!ok) {
                res.status = 502;
                res.set_content(json{{"success", false}, {"error_code", NET_ECMS_GetLastError()}}.dump(), "application/json");
                return;
            }
            size_t len = param.dwReturnedXMLLen > 0 ? param.dwReturnedXMLLen : strnlen(out.data(), out.size());
            res.set_content(json{{"success", true}, {"response", std::string(out.data(), len)}}.dump(), "application/json");
        } catch (const std::exception& e) {
            res.status = 400;
            res.set_content(json{{"success", false}, {"error", e.what()}}.dump(), "application/json");
        }
    });

    std::cout << "REST API listening on " << g_cfg.apiHost << ":" << g_cfg.apiPort << std::endl;
    api.listen(g_cfg.apiHost, g_cfg.apiPort);

    if (alarmHandle >= 0) {
        NET_EALARM_StopListen(alarmHandle);
        NET_EALARM_Fini();
    }
    NET_ECMS_StopListen(cmsHandle);
    NET_ECMS_Fini();
    return 0;
}
