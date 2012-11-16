#!/bin/bash
type=$1; shift
min=$1; shift
max=$1; shift
if [[ "$type" != anime && "$type" != manga ]]; then
	echo 'type must be anime or manga' 1>&2;
	exit 1;
fi
seq $min $max|xargs -n 1 -P 20 -I {} sh -c "{
	echo -n \"{} \";
	php ./get-amu.php \"$type\" \"{}\"
}"
