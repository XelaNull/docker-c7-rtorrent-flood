FROM c7/lamp:latest
# https://github.com/XelaNull/docker-c7
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
RUN { echo '<?php'; \
      echo "\$display = Array ('img','mp4','avi','mkv','m2ts','wmv','iso','divx','mpg','m4v');"; \
      echo "foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(basename(\"${DIR_OUTGOING}\"))) as \$file)"; \
      echo "{ if(basename($file)=='..' || basename($file)=='.' || strpos(strtolower(basename($file)),'sample')!==FALSE) continue; if (in_array(strtolower(array_pop(explode('.', \$file))), \$display))"; \
      echo 'echo "http://$_SERVER[HTTP_HOST]/". $file . "\n<br/>"; }'; echo '?>'; \
    } | tee /var/www/html/scan.php && touch /var/www/html/index.php

# rar, unrar
RUN wget https://www.rarlab.com/rar/rarlinux-x64-5.5.0.tar.gz && tar -zxf rarlinux-*.tar.gz && cp rar/rar rar/unrar /usr/local/bin/
# unrarall
RUN git clone http://github.com/arfoll/unrarall.git unrarall/ && chmod a+x unrarall/unrarall && cp unrarall/unrarall /usr/local/sbin/     
# ffmpeg
RUN wget http://li.nux.ro/download/nux/dextop/el7/x86_64/nux-dextop-release-0-5.el7.nux.noarch.rpm && yum -y localinstall nux-dextop-*.rpm && yum -y install ffmpeg ffmpeg-devel
    
# rTorrent config
RUN adduser rtorrent && { \
    echo "directory = ${DIR_INCOMING}"; \
    echo 'session = /srv/torrent/.session'; \
    echo "port_range = ${RTORRENT_PORT}-${RTORRENT_PORT}"; \
    echo 'port_random = no'; \
    echo 'check_hash = yes'; \
    echo "dht = ${DHT_ENABLE}"; \
    echo 'dht_port = 6881'; \
    echo "peer_exchange = ${USE_PEX}"; \
    echo 'use_udp_trackers = yes'; \
    echo 'encryption = allow_incoming,try_outgoing,enable_retry'; \
    echo "scgi_port = 127.0.0.1:${RTORRENT_SCGI_PORT}"; \
#    echo 'ratio.enable='; \
    echo "method.insert = d.get_finished_dir, simple, \"cat=${DIR_OUTGOING}/,\$d.custom1=\""; \
    echo 'method.insert = d.get_data_full_path, simple, "branch=((d.is_multi_file)),((cat,(d.directory))),((cat,(d.directory),/,(d.name)))"'; \
    echo 'method.insert = d.move_to_complete, simple, "execute=mkdir,-p,$argument.1=; execute=cp,-rp,$argument.0=,$argument.1=; d.stop=; d.directory.set=$argument.1=; d.start=;d.save_full_session=; execute=rm, -r, $argument.0="'; \
    echo 'method.set_key = event.download.finished,move_complete,"d.move_to_complete=$d.get_data_full_path=,$d.get_finished_dir="'; \
    echo 'log.open_file = "rtorrent", /var/log/rtorrent.log'; \
    echo 'log.open_file = "tracker", ~/log/tracker.log'; \
    echo 'log.open_file = "storage", ~/log/storage.log'; \
    echo 'log.add_output = "info", "rtorrent"'; \
    echo 'log.add_output = "critical", "rtorrent"'; \
    echo 'log.add_output = "error", "rtorrent"'; \
    echo 'log.add_output = "warn", "rtorrent"'; \
    echo 'log.add_output = "notice", "rtorrent"'; \
    echo 'log.add_output = "debug", "rtorrent"'; \
    echo 'log.add_output = "dht_debug", "tracker"'; \
    echo 'log.add_output = "tracker_debug", "tracker"'; \
    echo 'log.add_output = "storage_debug", "storage"'; \
    } | tee /home/rtorrent/.rtorrent.rc && chown rtorrent:rtorrent /home/rtorrent/.rtorrent.rc && \
    mkdir /srv/torrent && mkdir /srv/torrent/.session && chmod 775 -R /srv/torrent && chown rtorrent:rtorrent -R /srv/torrent && \
    mkdir ${DIR_INCOMING} && chown apache:rtorrent ${DIR_INCOMING} -R && chmod 775 ${DIR_INCOMING} && \
    mkdir ${DIR_OUTGOING} && chown apache:rtorrent ${DIR_OUTGOING} -R && chmod 775 ${DIR_OUTGOING}        
    
# Install Pyrocore, to get rtcontrol to stop torrents from seeding after xxx days
RUN cd /home/rtorrent && mkdir bin && git clone "https://github.com/pyroscope/pyrocore.git" pyroscope/ && \
    chown rtorrent /home/rtorrent -R && sudo -u rtorrent /home/rtorrent/pyroscope/update-to-head.sh 

# NodeJS & npm
RUN curl -sL https://rpm.nodesource.com/setup_11.x | bash - && yum install -y nodejs
# flood
RUN cd /srv/torrent && git clone https://github.com/jfurrow/flood.git && \
    cd flood && cp config.template.js config.js && sed -i "s|floodServerHost: '127.0.0.1'|floodServerHost: '0.0.0.0'|g" config.js && \
    npm install && npm install -g node-gyp && npm install --save semver && npm run build && chown -R rtorrent:rtorrent /srv/torrent/flood/ && \
    { echo '#!/bin/bash'; echo 'cd /srv/torrent/flood/ && /usr/bin/npm start'; } | tee /start_flood.sh    

# Compile cksfv
RUN git clone https://github.com/vadmium/cksfv.git && cd cksfv && ./configure && make && make install
RUN /gen_sup.sh rtorrent "sudo -u rtorrent /usr/bin/rtorrent" >> /etc/supervisord.conf && \
    /gen_sup.sh flood "sudo -u rtorrent /start_flood.sh" >> /etc/supervisord.conf

RUN echo "0 * * * * rtorrent /usr/local/sbin/unrarall ${DIR_OUTGOING}" > /etc/cron.d/rtorrent && \
    echo "30 * * * * rtorrent /home/rtorrent/bin/rtcontrol --cron seedtime=+${DELETE_AFTER_HOURS}h is_complete=y [ NOT up=+0 ] --cull --yes" > /etc/cron.d/rtorrent && \
    echo "35 * * * * rtorrent /home/rtorrent/bin/rtcontrol --cron seedtime=+${DELETE_AFTER_RATIO_REQ_SEEDTIME}h ratio=+${DELETE_AFTER_RATIO} is_complete=y [ NOT up=+0 ] --cull --yes" >> /etc/cron.d/rtorrent
    
# Ensure all packages are up-to-date, then fully clean out all cache
RUN chmod a+x /*.sh && yum -y update && yum clean all && rm -rf /tmp/* && rm -rf /var/tmp/*

# Set to start the supervisor daemon on bootup
ENTRYPOINT ["/start_supervisor.sh"]