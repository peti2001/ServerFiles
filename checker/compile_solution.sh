#!/bin/sh
# sh scriptname 1 2 3
gcc -x c $1 -o $2 -lm 2> $3