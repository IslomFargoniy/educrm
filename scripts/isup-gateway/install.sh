#!/usr/bin/env bash
# Copies the ISUP gateway (binary + Hikvision libraries) from the server that runs it now to a new one
# and starts it there. Run from the Mac after the CRM itself has been moved:
#   scripts/isup-gateway/install.sh admin@OLD_SERVER admin@NEW_SERVER /path/to/app/on/new/server SITE_USER
# Devices need no change: they connect to the CRM domain, which now points at the new server.
set -euo pipefail
FROM=${1:?old server}; TO=${2:?new server}; APP_DIR=${3:?app dir on the new server}; SITE_USER=${4:?site user}
DIR=$(cd "$(dirname "$0")" && pwd)

ssh "$FROM" 'sudo tar czf - -C /opt educrm-isup/educrm-isup educrm-isup/lib' | ssh "$TO" 'sudo mkdir -p /opt && sudo tar xzf - -C /opt'
scp -q "$DIR/educrm-isup.service" "$TO:/tmp/educrm-isup.service"
ssh "$TO" "set -e
TOKEN=\$(sudo grep '^ISUP_GATEWAY_TOKEN=' $APP_DIR/.env | cut -d= -f2)
HOST=\$(sudo grep '^APP_URL=' $APP_DIR/.env | cut -d= -f2 | sed -E 's#^https?://##; s#/.*##')
[ -n \"\$TOKEN\" ] || { echo 'ISUP_GATEWAY_TOKEN missing in .env'; exit 1; }
printf 'CRM_HOST=%s\nCRM_PORT=80\nCRM_HOSTNAME=%s\nPUBLIC_ADDRESS=%s\nCMS_PORT=7660\nALARM_PORT=7662\nAPI_HOST=127.0.0.1\nAPI_PORT=7671\nGATEWAY_TOKEN=%s\n' \"\$HOST\" \"\$HOST\" \"\$HOST\" \"\$TOKEN\" | sudo tee /opt/educrm-isup/educrm-isup.env >/dev/null
sudo chmod 600 /opt/educrm-isup/educrm-isup.env
sudo mv /tmp/educrm-isup.service /etc/systemd/system/educrm-isup.service
sudo systemctl daemon-reload && sudo systemctl enable --now educrm-isup && sudo systemctl restart educrm-isup
sleep 2; systemctl is-active educrm-isup"
echo "Gateway running on $TO. Open ports 7660 (tcp+udp) and 7662 (tcp) if a firewall is on."
