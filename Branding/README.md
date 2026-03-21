## Simple Installation Guide
Download the module folder to the Zabbix Web frontend Server and place in:
/usr/share/zabbix/modules/

## Set permissions
### Permissions, installation and SELinux
sudo chown -R root:root /usr/share/zabbix/modules/Branding<br>
sudo find /usr/share/zabbix/modules/Branding -type d -exec chmod 755 {} \;<br>
sudo find /usr/share/zabbix/modules/Branding -type f -exec chmod 644 {} \;<br>
<br>
sudo semanage fcontext -m -t httpd_sys_content_t '/usr/share/zabbix/modules/Branding(/.*)?'<br>
sudo restorecon -Rv /usr/share/zabbix/modules/Branding<br>
<br>
sudo install -d -o apache -g apache -m 0775 /usr/share/zabbix/modules/Branding/assets/logos<br>
sudo semanage fcontext -a -t httpd_sys_rw_content_t '/usr/share/zabbix/modules/Branding/assets/logos(/.*)?'<br>
sudo restorecon -Rv /usr/share/zabbix/modules/Branding/assets/logos<br>
<br>
sudo install -d -o apache -g apache -m 0775 /usr/share/zabbix/local/conf/rebrand<br>
sudo chgrp apache /usr/share/zabbix/local/conf<br>
sudo chmod 0775 /usr/share/zabbix/local/conf<br>
sudo semanage fcontext -a -t httpd_sys_rw_content_t '/usr/share/zabbix/local/conf(/.*)?'<br>
sudo restorecon -Rv /usr/share/zabbix/local/conf<br>
<br>
sudo systemctl restart php-fpm<br>
<br>
sudo -u apache test -w /usr/share/zabbix/modules/Branding/assets/logos && echo OK_logo_dir<br>
sudo -u apache test -w /usr/share/zabbix/local/conf && echo OK_conf<br>