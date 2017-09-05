REPORT_DIR = reports

unit: install
	vendor/bin/phpunit --color --log-junit ${REPORT_DIR}/phpunit.xml --coverage-clover ${REPORT_DIR}/coverage/clover.xml

checkstyle: install
	vendor/bin/phpcs -p  --extensions=php --exclude=Generic.Files.LineLength --standard=PSR2 --report=full --report-checkstyle=${REPORT_DIR}/checkstyle.xml src/

install:
	composer install
