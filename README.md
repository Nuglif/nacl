Nuglif Application Configuration Language (NACL)
================================================

NACL is a configuration language both human and machine friendly.
It's a JSON superset which means that JSON can be used as valid input to the NACL parser.
NACL is heavily inspired by [libucl](https://github.com/odiszapc/libucl).

For detailed grammar refer to the [EBNF](EBNF.md).

Table of content
----------------

* [Example](#example)
* [Install](#install)
* [Usage](#usage)
* [Additions to the JSON syntax](#additions-to-the-json-syntax)
  - [Explicit root object](#explicit-root-object)
  - [Unquoted strings](#unquoted-strings)
  - [Multiline string](#multiline-string)
  - [Values are assigned using either `=` `:` or no sign](#values-are-assigned-using-either-or-no-sign)
  - [Separator](#separator)
  - [Variables](#variables)
  - [Comments](#comments)
  - [Boolean values](#boolean-values)
  - [Multipliers](#multipliers)
  - [Object merge](#object-merge)
  - [Named keys hierarchy](#named-keys-hierarchy)
  - [Macros](#macros)
  	- [Include macro](#include-macro)
  	- [Reference macro](#reference-macro)
  	- [File macro](#file-macro)
  	- [Env macro](#env-macro)
  	- [Extending NACL with your own macro](#extending-nacl-with-your-own-macro)

Example
-------

```nacl
application {
	debug off;
	buffer 10MB;

	mysql {
		host .env (default: "127.0.0.1") MYSQL_HOST;
		username .env (default: root) MYSQL_USERNAME;
		password .env (default: root) MYSQL_PASSWORD;
		port .env (default: 3306, type: int) MYSQL_PORT;
	}

	servers [
		"172.28.0.10",
		"172.28.0.5"
	]
}
```

Install
-------

To install with composer:

```sh
composer require nuglif/nacl
```

Requires PHP 5.6 or newer.

Usage
-----

Here's a basic usage example:

```php
<?php

$config = Nuglif\Nacl\Nacl::parseFile('application.conf');
```
or
```php
<?php

$parser = Nuglif\Nacl\Nacl::createParser();
$config = $parser->parseFile('application.conf');
```

Additions to the JSON syntax
----------------------------

### Explicit root object

Allow omitting the `{}` around a root object.

```
"host": "localhost"
```
is equivalent to:

```json
{"host": "localhost"}
```

> **NOTE**: In NACL an empty file is valid and equivalent to an empty JSON object `{}`

### Unquoted strings
Allow unquoted strings for single word keys and values.

```
host: localhost
```
is equivalent to:

```json
{"host": "localhost"}
```
### Multiline string

You can create multiline string using the heredoc syntax.
```
text: <<<END
This is
a multiline
string
END;
```
is equivalent to:
```json
{"text": "This is\na multiline\nstring"}
```

> **NOTE**: If you have really long text, you might want to put the text in
> a single file and use the [file macro](#file-macro).

### Values are assigned using either `=` `:` or no sign

```nacl
host: localhost;
```
is equivalent to
```nacl
host = localhost;
```
is equivalent to
```nacl
host localhost;
```
is equivalent to
```json
{"host": "localhost"}
```

### Separator
Elements in an array or object are separated using either `;` or `,` and you can safely use an extra separator after the last element.

```nacl
key1 value,
key2 value2,
```
is equivalent to
```nacl
key1 value;
key2 value;
```
is equivalent to
```json
{
	"key1": "value",
	"key2": "value"
}
```

### Variables
Variables can be created or read using the `${VAR}` syntax.

```nacl
${TMP_DIR} = "/tmp";
temp_dir = ${TMP_DIR};
temp_file = "${TMP_DIR}/tempfile.txt";
```
is equivalent to:
```json
{
    "temp_dir": "/tmp",
    "temp_file": "/tmp/tempfile.txt"
}
```

> **NOTE**: It's also possible to inject variables using the API.
```php
<?php
$parser = Nuglif\Nacl\Nacl::createParser();
$parser->setVariable('TMP_DIR', sys_get_temp_dir());
$config = $parser->parseFile('application.conf');
```

### Comments
NACL supports multiple styles of comments.
* Single line comments start with `//` or `#`
* Multiple line comments start with `/*` and end with `*/`

### Boolean values
You can express boolean using `true` `on` `yes` or `false` `off` `no`.

```nacl
debug on;
```

### Multipliers

Number can be suffixed with
* `k` `M` `G` prefixes for the International System of Units (SI) (1000^n)
* `kB` `MB` `GB` 1024^n bytes
* `ms` `s` `min` `h` `d` `w` `y` number in seconds .

```nacl
file_max_size 10MB; # 10 * 1000^6
file_ttl 90min; # 90 * 60
```

### Object merge

Objects with the same name are merged.

```nacl
foo {
	a: true;
	b: { c: "c"}
}
foo {
	a: false;
	b: { x: "x" }
}
```

is equivalent to
```json
{
	"foo": {
		"a": false,
		"b": {
			"c": "c",
			"x": "x"
		}
	}
}
```

### Named keys hierarchy

```nacl
server url "localhost";
server port 80;

```
is equivalent to
```json
{
	"server": {
		"url": "localhost",
		"port": 80
	}
}
```

### Macros

### Include macro

`.include` predefined macro includes and evaluates the specified file(s).

```nacl
.include "file.conf";
.include (required: false) "file.override.conf";
.include (glob: true) "conf.d/*.conf";
```

| Option     | Default value | Description                                                                |
|------------|---------------|----------------------------------------------------------------------------|
| `required` | `true`        | If `false` nacl will not trigger any error if included file doesn't exist. |
| `glob`     | `false`       | If `true` nacl will include all files that match the pattern.              |


#### Reference macro

`.ref` predefined macro is a reference to another value in the tree. The provided path can be relative or absolute.

```nacl
foo bar;
bar .ref "foo";
```

#### File macro

`.file` read the specified file and return its content without evaluating it.

```
email {
	template .file "welcome.tpl";
}
```

#### Env macro
`.env` read the specified environment variable.

| Option    | Default value | Description                                                                                |
|-----------|---------------|--------------------------------------------------------------------------------------------|
| `default` | -             | If the environment variable doesn't exist, the default value will be returned.             |
| `type`    | -             | Since environment variables are always stored as string, it's possible to define the type. |


Example:
```
port .env (default: 80, type: int) SERVER_PORT;
```

#### Extending NACL with your own macro

It's easy to extend NACL using your own macro.

```nacl
key .myMacro someParam;
key .myMacro(optionName: someValue, otherOption: otherValue) {
	/* Some content here */
};
```

To create your macro you must implement the `Nuglif\Nacl\MacroInterface` interface
```php
<?php

interface MacroInterface
{
    /**
     * @return string
     */
    public function getName();

    /**
     * @param mixed $parameter;
     * @param array $options
     * @return mixed
     */
    public function execute($parameter, array $options = []);
}
```
and use the `Nulig\Nacl\Parser::registerMacro($macro);` method.

```php
<?php

Nuglif\Nacl\Nacl::registerMacro(new MyMacro);
$config = Nuglif\Nacl\Nacl::parseFile('application.conf');
```
or

```php
<?php

$parser = Nuglif\Nacl\Nacl::createParser();
$parser->registerMacro(new MyMacro);
$config = $parser->parseFile('application.conf');

```
