
Nuglif Application Configuration Language (NACL)
================================================

[![Build Status](https://travis-ci.com/Nuglif/nacl.svg?branch=master)](https://travis-ci.com/Nuglif/nacl)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Latest Stable Version](https://poser.pugx.org/nuglif/nacl/v/stable)](https://packagist.org/packages/nuglif/nacl)
[![Code Coverage](https://scrutinizer-ci.com/g/Nuglif/nacl/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/Nuglif/nacl/?branch=master)

*NACL* is a configuration data language intended to be both human and machine friendly. Although it's a *JSON* superset which means that *JSON* can be used as valid input to the *NACL* parser, the primary motivation behind NACL is representation and interpretation of configuration data, by opposition to traditional data representation languages like JSON or YAML, that define themselves as _data object representation_ and _data serialization respectively_, which would belong to the general data representation languages domain, and thus quickly show weaknesses within the application configuration domain.

Thanks to _Vsevolod Stakhov_ who created UCL after having felt that _XML_, as a configuration language, wasn't up to the task. *NACL* is heavily inspired by _Vsevolod Stakhov's_ [*UCL*](https://github.com/vstakhov/libucl) (Universal Configuration Language).

This project contains both the _NACL_ specification, and it's implementation as a ___PHP___ library. A detailed *NACL* grammar reference is also available in [EBNF](EBNF.md).



Table of content
----------------


- [Nuglif Application Configuration Language (NACL)](#nuglif-application-configuration-language-nacl)
  * [_NACL_ Example Source for the Impatient](#nacl-example-source-for-the-impatient)
  * [_NACL_ Extensions to the _JSON_ Syntax](#nacl-extensions-to-the-json-syntax)
    + [The Implicit Root Object](#the-implicit-root-object)
    + [The Unquoted Strings](#the-unquoted-strings)
    + [The Multiline Strings](#the-multiline-strings)
    + [The Optional Values Assignments Symbol](#the-optional-values-assignments-symbol)
    + [The Separator Symbol](#the-separator-symbol)
    + [The Variables](#the-variables)
    + [The Comments](#the-comments)
    + [The Boolean Values](#the-boolean-values)
    + [The Multipliers](#the-multipliers)
    + [The _NACL_ Object Structure Will Merge](#the-nacl-object-structure-will-merge)
    + [Using Key Names for Hierarchical Declaration](#using-key-names-for-hierarchical-declaration)
  * [The _NACL_ Macros](#the-nacl-macros)
    + [The _.ref_ Macro _(Referencing)_](#the-ref-macro-referencing)
    + [The _.include_ Macro _(Evaluated Inclusions)_](#the-include-macro-evaluated-inclusions)
    + [The _.file_ Macro _(Unevaluated Inclusions)_](#the-file-macro-unevaluated-inclusions)
    + [The _.env_ Macro _(Environment Variables)_](#the-env-macro-environment-variables)
- [The PHP Library](#the-php-library)
  * [Installation](#installation)
  * [Usage](#usage)
      - [Extending NACL With Your Own Macros](#extending-nacl-with-your-own-macros)

##  _NACL_ Example Source for the Impatient

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


## _NACL_ Extensions to the _JSON_ Syntax

Because _NACL_ is a superset of _JSON_, we will skim over the _JSON_ syntax itself and describe how the language was extended below.

### The Implicit Root Object

_NACL_ will provide an implicit `{}` root object.

For example
```nacl
"host": "localhost"
```
will be equivalent to
```json
{"host": "localhost"}
```

**Note:** An empty _NACL_ file is valid and is equivalent to an empty JSON object `{}`.

### The Unquoted Strings

_NACL_ allows unquoted strings for single word keys and values.

For example
```
host: localhost
```
will be equivalent to
```json
{"host": "localhost"}
```

### The Multiline Strings

_NACL_ allows multiline string using the _heredoc_ syntax.

For example
```
text: <<<END
This is
a multiline
string
END;
```
will be equivalent to
```json
{"text": "This is\na multiline\nstring"}
```

**Note:** If you have really long text, you might want to put the text in a single file and use the [file macro](#file-macro).

### The Optional Values Assignments Symbol

_NACL_ allows value assignment using the equal sign `=` or the the column `:`, however this assignment sign is optional and _NACL_ allows you to leave the assignment sign out entirely.

For example
```nacl
host: localhost;
```
is equivalent to
```nacl
host = localhost;
```
and also equivalent to
```nacl
host localhost;
```
which are all equivalents to
```json
{"host": "localhost"}
```


### The Separator Symbol

_NACL_ statements (array or object elements) are separated using either `;` or `,` and _NACL_ allows statements terminators, so you can safely use an extra separator after the last element of an array or object.

For example
```nacl
key1 value1,
key2 value2
```
is equivalent to
```nacl
key1 value1,
key2 value2,
```
which is also equivalent to
```nacl
key1 value1;
key2 value2;
```
which are all equivalents to
```json
{
	"key1": "value1",
	"key2": "value2"
}
```

### The Variables
Variables can be created or read using the `${VAR}` syntax.

For example
```nacl
${TMP_DIR} = "/tmp";
temp_dir = ${TMP_DIR};
temp_file = "${TMP_DIR}/tempfile.txt";
```
is equivalent to
```json
{
    "temp_dir": "/tmp",
    "temp_file": "/tmp/tempfile.txt"
}
```

> **PHP Related Note**: The PHP library allows injection of variables using the API, for example
> ```php
> <?php
> $parser = Nuglif\Nacl\Nacl::createParser();
> $parser->setVariable('TMP_DIR', sys_get_temp_dir());
> $config = $parser->parseFile('application.conf');
> ```

### The Comments

_NACL_ allows two single line comment styles and one multiline comment style.

* Single line comments can start with `//` or `#`
* Multiple line comments must start with `/*` and end with `*/`

### The Boolean Values

_NACL_ allows you to express your booleans using `true` / `false`, but also `yes` / `no` and `on` / `off`.  All will be interpreted as booleans, but having diversity in wording allows you to better express the intention behind a boolean configuration statement.

You can simply state
```nacl
debug on;
```
which is more natural than
```nacl
debug true;
```
or even worst
```nacl
debug 1;
```

### The Multipliers

Suffix multipliers make the _NACL_ more declarative and concise, and help you avoid mistakes. _NACL_ allows the use of some of the common suffix multipliers.

In _NACL_, number can be suffixed with
* `k` `M` `G` prefixes for the International System of Units (SI) (1000^n)
* `kB` `MB` `GB` 1024^n bytes
* `ms` `s` `min` `h` `d` `w` `y` number in seconds .

For example

```nacl
file_max_size 7MB; # 7 * 1024^2 (bytes)
file_ttl 9min;     # 9 * 60 (seconds)
```

is equivalent to

```nacl
file_max_size 7340032; # 7 * 1024^2 (bytes)
file_ttl 540;          # 9 * 60 (seconds)
```

which is equivalent to

```json
{
    "file_max_size": 7340032,
    "file_ttl": 540
}
```

### The _NACL_ Object Structure Will Merge

_NACL_ allows objects with the same key names to be redeclared along the way, objects keys with the same name will simply merge together recursively (deep merge).  Merge only applies to object values, where non-object values overlap, the last declared value will be the final value.

For example

```nacl
foo {
	"non-object-value-a" true;
	"non-object-value-b" [ 1, 2 ];
	"object-value" { c: "c"}
}
foo {
	"non-object-value-a" false;
	"non-object-value-b" [ 3, 4 ];
	"object-value" { x: "x" }
}
```

Will be recursively merged where there are object values, and the resulting structure will be equivalent to

```json
{
	"foo": {
		"non-object-value-a": false,
		"non-object-value-b": [ 3, 4 ],
		"object-value": {
			"c": "c",
			"x": "x"
		}
	}
}
```

### Using Key Names for Hierarchical Declaration

_NACL_ allows you to set a value within a hierarchy using only the key as the hierarchical path by placing  every keys of the hierarchy one after the other separated by spaces on the key side of the assignation.

For example

```nacl
development server debug on;
production server url "example.com";
production server port 80;
```

will also be an _NACL_ equivalent to

```nacl
development {
	server {
		debug on;
	}
}
production {
	server {
		url "example.com";
		port 80;
	}
}
```

which could also be an _NACL_ equivalent of

```nacl
development {
	server {
		debug on;
	}
}
production {
	server {
		url "example.com";
	}
}
production server {
	port 80;
}
```

which will be a _JSON_ equivalent to

```json
{
	"development": {
		"server": {
			"debug": true
		}
	},
	"production": {
		"server": {
			"url": "example.com",
			"port": 80
		}
	}
}
```

## The _NACL_ Macros

_NACL_ offers some baseline macros, the _.ref_, _.include_, _.file_ and _.env_ Macros.

To differentiate them from keys and other language elements, macros names begin with a dot. They expect one value (which can be a primitive or a non-primitive), and possibly distinct optional parameters.

For example 

```nacl
.a_macro_name (param1: foo, param2: bar) "the primitive, array or object value"
```

would be a general _NACL_ macro form representation.

The macro specification allows the language to be extended with custom macros specific to your domain and implementation.

### The _.ref_ Macro _(Referencing)_

_NACL_ offers the `.ref` macro, which can be used as a reference to another value within the _NACL_ tree. The value you provide is a path which can be relative or absolute.

For example

```nacl
foo bar;
baz .ref "foo";
```

which will become the _JSON_ equivalent of

```json
{
	"foo": "bar",
	"baz": "bar"
}
```

### The _.include_ Macro _(Evaluated Inclusions)_

_NACL_ offers the `.include` macro, which can be used to include and evaluate _NACL_ files in other _NACL_ files.  The `.include` macro has two optional parameters which are described in the table below.

| Option     | Default value | Description                                                                |
|------------|---------------|----------------------------------------------------------------------------|
| `required` | `true`        | If `false` nacl will not trigger any error if included file doesn't exist. |
| `glob`     | `false`       | If `true` nacl will include all files that match the pattern. If the provided inclusion path has a wildcard while `glob` is set to false, _NACL_ will attempt to include a file matching the exact name, including its wildcard.              |

For example

```nacl
.include "file.conf";
.include (required: false) "file.override.conf";
.include (glob: true) "conf.d/*.conf";
```

As an other example, if you have a file named `file.conf` that contains only `foo: "bar";`, then the following _NACL_ example

```nacl
.include "file.conf";
baz: "qux";
```

will become the _JSON_ equivalent of

```json
{
	"foo": "bar",
	"baz": "qux"
}
```

### The _.file_ Macro _(Unevaluated Inclusions)_

_NACL_ offers the `.file` macro, which can be used to include other files within _NACL_ files without evaluating them.

For example

```
email {
	template .file "welcome.tpl";
}
```

will assign the content of the `welcome.tpl` file to the `template` variable.  If the `welcome.tpl` file contained only `Welcome my friend`, the previous _NACL_ example would become the _JSON_ equivalent of

```json
{
	"email": {
		"template": "Welcome my friend"
	}
}
```

### The _.env_ Macro _(Environment Variables)_

_NACL_ offers the `.env` macro, which can be used to evaluate the specified environment variable.  The `.env` macro has two optional parameters which are described in the table below.

| Option    | Default value | Description                                                                                |
|-----------|---------------|--------------------------------------------------------------------------------------------|
| `default` | -             | If the environment variable doesn't exist, this default value will be returned instead.             |
| `type`    | -             | Since environment variables are always string types, setting a  type will cast the string value to the provided type within _NACL_. |

For example

```nacl
port .env (default: 80, type: int) SERVER_PORT;
title .env TITLE;
```

on a system where the `SERVER_PORT` is undefined, and `TITLE` is set to `"300"`, the the previous _NACL_ example would become the _JSON_ equivalent of

```json
{
	"port": 80,
	"title": "300"
}
```

# The PHP Library

This project provides an _NACL_ specification implemented as a _PHP_ library.

## Installation

To install with composer:

```sh
composer require nuglif/nacl
```

The library will work on on versions of PHP from 5.6 to 7.0 or newer.

## Usage

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



#### Extending NACL With Your Own Macros

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


# Authors

* _**[Pierrick Charron](https://github.com/adoy)** (pierrick@adoy.net) - Initial work_
* _**[Charle Demers](https://github.com/cdemers)**  (charle.demers@gmail.com) - Initial work_

# License

This project is licensed under the MIT License - for the full copyright and license information, please view the [LICENSE](LICENSE) file that was distributed with this source code.

---
_Copyrights 2019 Nuglif (2018) Inc. All rights reserved._

