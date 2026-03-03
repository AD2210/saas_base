[Unit]
Description=Periodic SaaS PostgreSQL backup

[Timer]
OnCalendar=__BACKUP_ONCALENDAR__
Persistent=true
Unit=saas-db-backup.service

[Install]
WantedBy=timers.target
