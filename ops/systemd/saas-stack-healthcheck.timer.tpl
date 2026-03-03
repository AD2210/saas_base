[Unit]
Description=Run SaaS stack healthcheck periodically

[Timer]
OnCalendar=*:0/5
Persistent=true
Unit=saas-stack-healthcheck.service

[Install]
WantedBy=timers.target
