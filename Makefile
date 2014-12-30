clean:
	rm -rf build/artifacts/*

test:
	vendor/bin/phpunit --testsuite=unit $(TEST)

spec:
	vendor/bin/phpunit --testdox --testsuite=unit $(TEST)

travis:
	vendor/bin/phpunit --colors --testsuite=unit --coverage-text

coverage:
	vendor/bin/phpunit --testsuite=unit --coverage-html=build/artifacts/coverage $(TEST)

coverage-show:
	open build/artifacts/coverage/index.html

integ:
	vendor/bin/phpunit --debug --testsuite=integ $(TEST)

models:
	php build/api.php $(SRC)
