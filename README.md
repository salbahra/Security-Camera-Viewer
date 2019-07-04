[Security Camera Viewer](http://salbahra.github.io/Security-Camera-Viewer/)
=====================

A mobile frontend for the Logitech Alert security cameras. Desigened to allow live viewing, recorded video, and motion alerts. Other cameras may work but are untested.

Instructions:
-------------

+ You first need a working Logitech Alert setup (other cameras currently unsupported)
  + Send motion alerts to a dedicated email address which the poller will check

+ Install prerequisites as needed
  + ```apt-get install ffmpeg vlc apache2 mysql-server mysql-client php5-mysql php5 libapache2-mod-php5``` 
  + Depending on your distribution you might have to add multimedia sources to apt or package distribution system

+ Download the files
  + ```git clone https://github.com/salbahra/Security-Camera-Viewer.git```
  + ```mv camera-viewer/cameras /var/```
  + ```mv camera-viewer/www/cameras /var/www/```
  + ```mv camera-viewer/VLC-init /etc/init.d/```

+ Fill in the MySQL information, Email information, and site information in config.php.
  + ```nano /var/www/cameras/config.php```

+ Modify the feeds.vlm to transcode your videos and auto start VLC
  + ```nano /var/cameras/feeds.vlm```
  + ```update-rc.d vlc defaults```
  + ```/etc/init.d/vlc start```

+ Add the poller to crontab every 5 minutes to ensure your database is up to date with new video clips and motion alerts:
  + ```*/5 * * * *     /usr/bin/php /var/www/cameras/poller.php >/dev/null 2>&1```

+ Add a user to the configuration. There is no user management system yet, so this is done manually
  + First generate a hased version of your password
  + ```/usr/bin/php -r "echo base64_encode(sha1('PASSWORD'));"```
  + Then add it to the htpasswd file
  + ```nano /var/cameras/htpasswd```
  + Example:
  + ```username:ZGM3MjRhZjE4ZmJkZDRlNTkxODlmNWZlNzY4YTVmODMxMTUyNzA1MA==```

+ From there you may attempt to access the front end.
