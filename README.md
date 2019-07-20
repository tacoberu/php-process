php-process
===========

Objects help handling command lines specifying processes to execute. With fluent interfaces.


## Use

    $res = (new Process\Exec('ping 127.0.0.1 -c 3'))->run();


    $res = (new Process\Exec('ping'))
        ->arg('127.0.0.1')
        ->arg('-c 3');
        ->run();
    echo "{$res->code}\n{$res->content}\n";


    $code = (new Process\Exec('bin/readwrite.php'))
        ->run(function($out, $err) {
        echo '> ' . $out;
            return "Hi\n";
        });
