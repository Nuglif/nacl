# NACL EBNF

```ebnf
Nacl         ::= Array | Object
Object       ::= "{" InnerObject "}" | InnerObject
InnerObject  ::= [ KeyValueList [ "," | ";" ] ]
KeyValueList ::= KeyValue [ [ "," | ";" ] KeyValueList ]
KeyValue     ::= ( ( T_END_STR | T_NAME | T_VAR ) [ ":" | "=" ] Value ) | MacroCall
Array        ::= "[" [ ValueList ] "]"
ValueList    ::= Value [ "," ValueList ]
Value        ::= {T_END_STR | T_NAME }* ( String | Scalar | Variable | "{" InnerObject "}" | Array | MacroCall )
Scalar       ::= T_END_STR | T_NAME | T_BOOL | T_NUM | T_NULL
String       ::= { T_ENCAPSED_VAR | T_STRING }* T_END_STR
Variable     ::= T_VAR
MacroCall    ::= "." T_NAME [ "(" [ Object ] ")" ] Value
```
