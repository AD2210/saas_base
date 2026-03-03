[Unit]
Description=SaaS stack healthcheck and auto-repair
After=docker.service
Requires=docker.service

[Service]
Type=oneshot
EnvironmentFile=-/etc/default/saas-stack-healthcheck
ExecStart=/usr/local/bin/saas-stack-healthcheck.sh
StandardOutput=append:/var/log/saas/saas-stack-healthcheck.log
StandardError=append:/var/log/saas/saas-stack-healthcheck.log
