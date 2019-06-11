# NACL EBNF

```ebnf
Nacl         ::= Array | Object
Object       ::= "{" InnerObject "}" | InnerObject
InnerObject  ::= [ KeyValueList [ Separator ] ]
KeyValueList ::= KeyValue [ Separator KeyValueList ]
KeyValue     ::= ( ( T_END_STR | T_NAME | T_VAR ) [ ":" | "=" ] Value ) | MacroCall
Separator    ::= ";" | ","
Array        ::= "[" [ ValueList ] "]"
ValueList    ::= Value [ Separator ValueList ]
Value        ::= {T_END_STR | T_NAME }* ( String | Scalar | MathExpr | Variable | "{" InnerObject "}" | Array | MacroCall )
Scalar       ::= T_END_STR | T_NAME | T_BOOL | T_NUM | T_NULL
String       ::= { T_ENCAPSED_VAR | T_STRING }* T_END_STR
Variable     ::= T_VAR
MacroCall    ::= "." T_NAME [ "(" [ Object ] ")" ] Value
MathExpr     ::= OrOperand { "|" OrOperand }*
OrOperand    ::= AndOperand { "&" AndOperand }*
AndOperand   ::= ShiftOperand { ( "<<" | ">>" ) ShiftOperand }*
ShiftOperand ::= MathTerm { ( "+" | "-" ) MathTerm }*
MathTerm     ::= MathFactor { ( ( "*" | "%" | "/" ) MathFactor ) | ( "(" MathExpr ")" ) }*
MathFactor   ::= ( "(" MathExpr ")" ) | T_NUM | T_VAR | ( ("+"|"-") MathTerm ) [ "^" MathFactor ]
```
