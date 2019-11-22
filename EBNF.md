# NACL EBNF

```ebnf
Nacl                        ::= RootValue | InnerObject
RootValue                   ::= [ VariableAssignationList ] Value
VariableAssignationList     ::= VariableAssignation [ Separator [ VariableAssignationList ] ]
VariableAssignation         ::= T_VAR OptionalAssignementOperator Value
OptionalAssignementOperator ::= [ ":" | "=" ]
Object                      ::= "{" InnerObject "}"
InnerObject                 ::= [ KeyValueList ]
KeyValueList                ::= VariableAssignation|KeyValue [ Separator [ KeyValueList ] ]
KeyValue                    ::= ( ( T_END_STR | T_NAME ) OptionalAssignementOperator Value ) | MacroCall
Separator                   ::= ";" | ","
Array                       ::= "[" [ ValueList ] "]"
ValueList                   ::= Value [ Separator [ ValueList ] ]
Value                       ::= {T_END_STR | T_NAME }* ( String | Scalar | MathExpr | Variable | Object | Array | MacroCall )
Scalar                      ::= T_END_STR | T_NAME | T_BOOL | T_NUM | T_NULL
String                      ::= { T_ENCAPSED_VAR | T_STRING }* T_END_STR
Variable                    ::= T_VAR
MacroCall                   ::= "." T_NAME [ "(" InnerObject ")" ] Value
MathExpr                    ::= OrOperand { "|" OrOperand }*
OrOperand                   ::= AndOperand { "&" AndOperand }*
AndOperand                  ::= ShiftOperand { ( "<<" | ">>" ) ShiftOperand }*
ShiftOperand                ::= MathTerm { ( "+" | "-" ) MathTerm }*
MathTerm                    ::= MathFactor { ( ( "*" | "%" | "/" ) MathFactor ) | ( "(" MathExpr ")" ) }*
MathFactor                  ::= (( "(" MathExpr ")" ) | T_NUM | T_VAR | ( ("+"|"-") MathTerm )) [ "^" MathFactor ]
```
