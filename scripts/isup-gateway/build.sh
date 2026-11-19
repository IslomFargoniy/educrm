#!/usr/bin/env bash
# Builds the gateway on a Linux x86-64 machine that has g++ and the Hikvision ISUP SDK.
#   SDK_DIR  folder with include/ (HCISUPCMS.h, HCISUPAlarm.h, ...) and lib/ (libHCISUP*.so ...)
#   DEPS_DIR folder with httplib.h and json.hpp (single-header libraries)
set -euo pipefail
cd "$(dirname "$0")"
: "${SDK_DIR:?set SDK_DIR}"
: "${DEPS_DIR:?set DEPS_DIR}"
g++ -std=c++17 -O2 -o educrm-isup main.cpp \
    -I"$SDK_DIR/include" -I"$DEPS_DIR" \
    -L"$SDK_DIR/lib" -lHCISUPCMS -lHCISUPAlarm -lpthread \
    -Wl,-rpath,/opt/educrm-isup/lib
echo "built $(pwd)/educrm-isup"
