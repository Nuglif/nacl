# Nuglif Application Configuration Language (NACL)

NACL is a configuration language both human and machine friendly.
It's a JSON superset which means that JSON can be used as valid input to the NACL parser.
NACL is heavily inspired by libucl.

For the detailed grammar refer to the [EBNF](EBNF.md).

## Example

```nacl
foo {
	bar 10;
	baz {
		enable yes;
	}
}
```
#### Explicit root object
Allow omitting the `{}` around a root object
```
"foo": "bar"
```
is equal to
```json
{"foo": "bar"}
```

#### Unquoted strings
Allow unquoted strings for single word keys and values

```
foo: bar
```
is equal to:
```
{"foo": "bar"}
```

#### Values are assigned using either `=` `:` or no sign

```nacl
foo: bar;
```
is equal to
```nacl
foo = bar;
```
is equal to
```nacl
foo bar;
```
is equal to
```json
{"foo": "bar"}
```

#### Separator
Elements in an array or object are separated using either `;` or `,` and you can safely use an extra separator after the last element.

```nacl
key1 value,
key2 value2,
```
is equal to
```nacl
key1 value;
key2 value;
```
is equal to
```json
{
	"key1": "value",
	"key2": "value"
}
```

#### Variables
Variables can be created or readed using the `${VAR}` syntax.

```nacl
${TMP_DIR} = "/tmp";
temp_dir = ${TMP_DIR};
temp_file = "${TMP_DIR}/tempfile.txt";
```
is equal to:
```json
{
    "temp_dir": "/tmp",
    "temp_file": "/tmp/tempfile.txt"
}
```

#### Comments
NACL supports multiple styles of comments.
* Single line comments start with `//` or `#`
* Multiple line comments start with `/*` and end with `*/`

#### Boolean values
You can express boolean using `true` `on` `yes` or `false` `off` `no`

```nacl
debug on;
```

#### Multipliers

Number can be suffixed with `m(in|s)|[KkGgMm][Bb]?|[s|h|d|w|y]`
* `k` `M` `G` prefixes for the International System of Units (SI) (1000^n)
* `kB` `MB` `GB` 1024^n bytes
* `ms` `s` `min` `h` `d` `w` `y` number in seconds .

```nacl
file_max_size 10MB; # 10 * 1000^6
file_ttl 90min; # 90 * 60
```

#### Deep merge

Object with the same name are deep merged.

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

is equal to
```nacl
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
#### Include

`.include` predefined macro is similar to C include. It will be similar to getting the content
of the included file and copy pasting it at the same place as the include.

```nacl
.include "file.conf";
```

#### Reference

`.ref` predefined macro is a reference to an other value in the tree. The provided path can be
relative or absolute

```nacl
foo bar;
bar .ref "foo";
```

#### Macros
You can write your own external macros.

```nacl
home_dir = .env "HOME";
```
Macro can receive optional arguments
```
foobar = .consul (default: false, type: bool) "foo/bar";
```

To create your macro you must implement the `Nuglif\Nacl\MacroInterface` interface.
```php
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
And use the `Nulig\Nacl\Parser::registerMacro($macro);` method
```php
$parser->registerMacro(new Nuglif\Nacl\Macros\Env);
```
