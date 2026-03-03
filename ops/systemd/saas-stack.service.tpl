[Unit]
Description=SaaS stack (Docker Compose)
After=network-online.target docker.service
Wants=network-online.target
Requires=docker.service

[Service]
Type=oneshot
RemainAfterExit=yes
WorkingDirectory=__APP_ROOT__
ExecStart=/usr/bin/docker compose up -d --remove-orphans
ExecStop=/usr/bin/docker compose down
TimeoutStartSec=0
TimeoutStopSec=180
StandardOutput=append:/var/log/saas/saas-stack.log
StandardError=append:/var/log/saas/saas-stack.log

[Install]
WantedBy=multi-user.target
