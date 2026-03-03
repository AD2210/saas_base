[Unit]
Description=SaaS encrypted PostgreSQL backup
After=docker.service
Requires=docker.service

[Service]
Type=oneshot
ExecStart=/usr/local/bin/saas-backup-db.sh
StandardOutput=append:/var/log/saas/saas-db-backup.log
StandardError=append:/var/log/saas/saas-db-backup.log
