[Unit]
Description=Weekly reboot for maintenance window

[Timer]
OnCalendar=__WEEKLY_REBOOT_ONCALENDAR__
Persistent=true
Unit=saas-weekly-reboot.service

[Install]
WantedBy=timers.target
