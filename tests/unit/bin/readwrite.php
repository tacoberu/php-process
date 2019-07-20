#!/bin/env php
<?php

$count = 0;
while (True) {
	echo "< ";
	$str = fgets(STDIN);
	if (trim($str) == ':q') {
		break;
	}
	fwrite(STDOUT, "> $str\n");
	$count++;
}

exit(0);
