# https://github.com/XelaNull/docker-c7
FROM c7/lamp:latest 

ENV TIMEZONE="America/New_York"
ENV DIR_INCOMING="/var/www/html/incomplete"
ENV DIR_OUTGOING="/var/www/html/complete"
ENV RTORRENT_SCGI_PORT="5000"
ENV RTORRENT_PORT="50000"
ENV DELETE_AFTER_HOURS="75"
ENV DELETE_AFTER_RATIO="1.0"
ENV DELETE_AFTER_RATIO_REQ_SEEDTIME="12"
ENV DHT_ENABLE="disable"
ENV USE_PEX="no"

RUN yum -y install perl make gcc-c++ rsync nc openssh screen unzip rtorrent file mediainfo

# Drop in place the PHP file to scan for completed files to provide the URL to
RUN printf '<?php \n$display = Array ("img","mp4","avi","mkv","m2ts","wmv","iso","divx","mpg","m4v");\n\
foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(basename("${DIR_OUTGOING}"))) as $file)\n\
{ if(basename($file)==".." || basename($file)=="." || strpos(strtolower($file),"sample")!==FALSE) continue;\n\
if (in_array(strtolower(array_pop(explode(".", $file))), $display)) echo "http://$_SERVER[HTTP_HOST]/$file\n<br/>";\n' >> /var/www/html/scan.php && \
    touch /var/www/html/index.php

# rar, unrar
RUN wget https://www.rarlab.com/rar/rarlinux-x64-5.5.0.tar.gz && tar -zxf rarlinux-*.tar.gz && cp rar/rar rar/unrar /usr/local/bin/ && \
# unrarall
    git clone http://github.com/arfoll/unrarall.git unrarall/ && chmod a+x unrarall/unrarall && cp unrarall/unrarall /usr/local/sbin/ && \
# ffmpeg
    yum -y localinstall http://li.nux.ro/download/nux/dextop/el7/x86_64/nux-dextop-release-0-5.el7.nux.noarch.rpm && yum -y install ffmpeg ffmpeg-devel

# rTorrent config
RUN adduser rtorrent && mkdir /home/rtorrent/log && mkdir -p /srv/torrent/.session && \
    chmod 775 -R /srv/torrent && chown rtorrent:rtorrent -R /srv/torrent && \
    mkdir ${DIR_INCOMING} && chown apache:rtorrent ${DIR_INCOMING} -R && chmod 775 ${DIR_INCOMING} && \
    mkdir ${DIR_OUTGOING} && chown apache:rtorrent ${DIR_OUTGOING} -R && chmod 775 ${DIR_OUTGOING} && \
    printf '#!/bin/bash\n/usr/bin/sleep 15\ncd /home/rtorrent && wget https://raw.githubusercontent.com/XelaNull/docker-c7-rtorrent-flood/master/rtorrent.rc\n\
rm -rf .rtorrent.rc && mv rtorrent.rc .rtorrent.rc\n\
cat <<EOT >> /home/rtorrent/.rtorrent.rc\n' > /start_rtorrent.sh && \
    echo "directory = ${DIR_INCOMING}" >> /start_rtorrent.sh && \
    echo "port_range = ${RTORRENT_PORT}-${RTORRENT_PORT}" >> /start_rtorrent.sh && \
    echo "dht = ${DHT_ENABLE}" >> /start_rtorrent.sh && \
    echo "peer_exchange = ${USE_PEX}" >> /start_rtorrent.sh && \
    echo "scgi_port = 127.0.0.1:${RTORRENT_SCGI_PORT}" >> /start_rtorrent.sh && \
    echo "method.insert = d.get_finished_dir, simple, \"cat=${DIR_OUTGOING}/\"" >> /start_rtorrent.sh
    printf 'EOT\n/usr/bin/rtorrent' >> /start_rtorrent.sh
    
# Install Pyrocore, to get rtcontrol to stop torrents from seeding after xxx days
RUN cd /home/rtorrent && mkdir bin && git clone "https://github.com/pyroscope/pyrocore.git" pyroscope/ && \
    chown rtorrent /home/rtorrent -R && sudo -u rtorrent /home/rtorrent/pyroscope/update-to-head.sh 

# NodeJS & npm
RUN curl -sL https://rpm.nodesource.com/setup_11.x | bash - && yum install -y nodejs
# flood
RUN cd /srv/torrent && git clone https://github.com/jfurrow/flood.git && \
    cd flood && cp config.template.js config.js && sed -i "s|floodServerHost: '127.0.0.1'|floodServerHost: '0.0.0.0'|g" config.js && \
    npm install && npm install -g node-gyp && npm install --save semver && npm run build && chown -R rtorrent:rtorrent /srv/torrent/flood/ && \
    printf '#!/bin/bash\n/usr/bin/sleep 20 && cd /srv/torrent/flood/ && /usr/bin/npm start\n' > /start_flood.sh    

# Compile cksfv
RUN git clone https://github.com/vadmium/cksfv.git && cd cksfv && ./configure && make && make install
RUN /gen_sup.sh rtorrent "sudo -u rtorrent /start_rtorrent.sh" >> /etc/supervisord.conf && \
    /gen_sup.sh flood "sudo -u rtorrent /start_flood.sh" >> /etc/supervisord.conf

RUN printf '0 * * * * rtorrent /usr/local/sbin/unrarall ${DIR_OUTGOING}\n\
30 * * * * rtorrent /home/rtorrent/bin/rtcontrol --cron seedtime=+${DELETE_AFTER_HOURS}h is_complete=y [ NOT up=+0 ] --cull --yes\n\
35 * * * * rtorrent /home/rtorrent/bin/rtcontrol --cron seedtime=+${DELETE_AFTER_RATIO_REQ_SEEDTIME}h ratio=+${DELETE_AFTER_RATIO} is_complete=y [ NOT up=+0 ] --cull --yes' > /etc/cron.d/rtorrent
    
# Ensure all packages are up-to-date, then fully clean out all cache
RUN chmod a+x /*.sh && yum -y update && yum clean all && rm -rf /tmp/* && rm -rf /var/tmp/*
# Set to start the supervisor daemon on bootup
ENTRYPOINT ["/start_supervisor.sh"]