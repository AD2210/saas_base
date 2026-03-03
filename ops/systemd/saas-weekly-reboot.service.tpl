[Unit]
Description=SaaS weekly maintenance reboot

[Service]
Type=oneshot
ExecStart=/usr/bin/systemctl reboot
