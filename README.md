##background-work
/usr/local/bin/background-work

```shell
#!/bin/sh

# run with user root

# init config
WATCH_DIR=
PHP=
WEB_SERVER_USER=
# get config
while IFS='=' read -r key value; do
    case "$key" in
        'WATCH_DIR') WATCH_DIR="$value" ;;
        'WEB_SERVER_USER') WEB_SERVER_USER="$value" ;;
        'PHP') PHP="$value" ;;
    esac
done < /var/www/background-work.config 

# if true
if [ -f "$PHP" ] && [ -d "$WATCH_DIR" ]; then
	echo "start watch background work"
	if command -v inotifywait > /dev/null 2>&1; then
	    #inotifywait is installed
		if [ ! "$WEB_SERVER_USER" ]; then
		    #WEB_SERVER_USER is zero-length"
	        while true
	        do
	            inotifywait -m -e close_write $WATCH_DIR/. | while read path action serial_file
	            do
	                if  [ "${serial_file##*.}" = "ser" ]; then
	                    echo " " >> ${WATCH_DIR}/background-work.log
	                    echo "[`date '+%Y-%m-%d %T'`]" >> ${WATCH_DIR}/background-work.log
	                    php -d memory_limit=4G $PHP "$WATCH_DIR/$serial_file" >> ${WATCH_DIR}/background-work.log &
	                    sleep 1
	                    rm -f "${WATCH_DIR}/${serial_file}"
	                fi
	            done
	        done
	    else
	        while true
	        do
	            inotifywait -m -e close_write $WATCH_DIR/. | while read path action serial_file
	            do
	                if  [ "${serial_file##*.}" = "ser" ]; then
	                    echo " " >> ${WATCH_DIR}/background-work.log
	                    echo "[`date '+%Y-%m-%d %T'`]" >> ${WATCH_DIR}/background-work.log
	                    su $WEB_SERVER_USER -c "`php -d memory_limit=4G $PHP \"${WATCH_DIR}/${serial_file}\"` >> ${WATCH_DIR}/background-work.log" &
	                    sleep 1
	                    rm -f "${WATCH_DIR}/${serial_file}"
	                fi
	            done
	        done
	    fi
	else
	    #inotifywait is not installed
		if [ ! "$WEB_SERVER_USER" ]; then
		    #WEB_SERVER_USER is zero-length"
		    # Repeat endlessly to check the serial data file *.ser
	        while true; do
	            for serial_file in "${WATCH_DIR}"/*.ser; do
	                if [ -f "${serial_file}" ]; then
	                    # File found *.ser
	                    # Read the contents of the file into the variable
	                    echo " " >> ${WATCH_DIR}/background-work.log
	                    echo "[`date '+%Y-%m-%d %T'`]" >> ${WATCH_DIR}/background-work.log
	                    php -d memory_limit=4G $PHP "${serial_file}" >> ${WATCH_DIR}/background-work.log &
	                    sleep 1
	                    rm -f "${serial_file}"
	                fi
	            done
	            sleep 1  # Pause 1 second before checking again
	        done
	    else
		    # Repeat endlessly to check the serial data file *.ser
	        while true; do
	            for serial_file in "${WATCH_DIR}"/*.ser; do
	                if [ -f "${serial_file}" ]; then
	                    # File found *.ser
	                    # Read the contents of the file into the variable
	                    echo " " >> ${WATCH_DIR}/background-work.log
	                    echo "[`date '+%Y-%m-%d %T'`]" >> ${WATCH_DIR}/background-work.log
	                    su $WEB_SERVER_USER -c " `php -d memory_limit=4G $PHP \"${serial_file}\"` >> ${WATCH_DIR}/background-work.log " &
	                    sleep 1
	                    rm -f "${serial_file}"
	                fi
	            done
	            sleep 1  # Pause 1 second before checking again
	        done
	    fi
	fi

else
	echo "[`date '+%Y-%m-%d %T'`] There was an error starting watch the background work." >> /var/www/error-background-work.log
	echo "WATCH_DIR: '$WATCH_DIR'"  >> /var/www/error-background-work.log
	echo "WEB_SERVER_USER: '$WEB_SERVER_USER'"  >> /var/www/error-background-work.log
	echo "PHP: '$PHP'"  >> /var/www/error-background-work.log

fi
```

