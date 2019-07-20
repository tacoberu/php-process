<?php
/**
 * @copyright 2016 Martin Takáč (http://martin.takac.name)
 * @license   https://opensource.org/licenses/MIT MIT
 */

namespace Taco\Utils\Process;


/**
 * $res = (new Process\Exec('ping 127.0.0.1 -c 3'))->run();
 *
 * $res = (new Process\Exec('ping'))
 * 		->arg('127.0.0.1')
 * 		->arg('-c 3')
 * 		->setWorkingDirectory('..')
 * 		->run();
 *
 * $code = (new Process\Exec('bin/readwrite.php'))
 * 		->run(function($out, $err) {
 *			echo '> ' . $out;
 * 			return "Hi\n";
 * 		});
 *
 * @author Martin Takáč <martin@takac.name>
 */
class Exec
{

	const CHUNK_SIZE = 16384;

	/**
	 * Příkaz.
	 * @var String
	 */
	private $command;

	/**
	 * Pracovní adresář, kde se bude příkaz zpracovávat.
	 * @var String
	 */
	private $workDirectory;

	/**
	 * Seznam parametrů.
	 * @var array
	 */
	private $arguments = array();


	/**
	 * Vytvoření příkazu.
	 * @param string Příkaz procesu. Případné parametry možno taky, ale lepší to zadat do args.
	 */
	function __construct($command)
	{
		if ( ! \function_exists('proc_open')) {
			throw new LogicException('The Process class relies on proc_open, which is not available on your PHP installation.');
		}
		$this->command = $command;
	}



	/**
	 * Builds the full command to execute and stores it in $command.
	 *
	 * @param string
	 * @return void
	 * @uses   $command
	 */
	function setWorkDirectory($dir)
	{
		if ( ! file_exists($dir)) {
			throw new \RuntimeException("Directory '$dir' is not found.");
		}
		$this->workDirectory = $dir;
		return $this;
	}



	/**
	 * Builds the full command to execute and stores it in $command.
	 *
	 * @return string
	 * @uses   $command
	 */
	protected function buildCommand($err2out = True)
	{
		$args = $this->arguments;
		if ($err2out) {
			$args[] = '2>&1';
		}
		return $this->command . ' ' . implode(' ', $args);
	}



	/**
	 * Executes the command and returns return code and output.
	 *
	 * @return array array(return code, array with output)
	 */
	function run()
	{
		$command = $this->buildCommand();
		$output = array();
		$code = null;

		if ($this->workDirectory) {
			$currdir = getcwd();
			@chdir($this->workDirectory);
		}

		//$this->trace("Executing command: " . $command);
		exec($command, $output, $code);

		if (isset($currdir)) {
			@chdir($currdir);
		}

		$error = implode(PHP_EOL, $output);

		if ($code > 0) {
			throw new ExecException($command, $error, '', $code);
		}

		return (object)array(
			'code' => $code,
			'content' => $output
		);
	}



	/**
	 * Executes the command and returns return code. Param is agent (callback) for
	 * comunication with script. Callback return input for script. Callback can throw
	 * exception for error, or exception for interrupt signal.
	 *
	 * @param function($out:string, $err:string) : string
	 * @return array array(return code, array with output)
	 */
	function runAgent($cb)
	{
		$process = proc_open($this->buildCommand(False), [
			0 => array('pipe', 'r'),
			1 => array('pipe', 'w'),
			//~ 2 => array('pipe', 'w'), TODO zatím nevím, jak obsluhovat.
		], $pipes, $this->workDirectory);
		if (empty($process) || ! is_resource($process)) {
			throw new ExecException($command, 'Cannot start process.', '', 1);
		}

		// Přečteme výstup. Pokud je prázdný, přečteme a zapíšeme vstup, pokud je vstup prázdný, čekáme vteřinu.
		try {
			while (True) {
				if (feof($pipes[1])) {
					break;
				}
				$output = self::read($pipes[1]);
				//~ $error = self::read($pipes[2]); TODO zatím nevím, jak obsluhovat.
				$error = '';
				$res = $cb($output, $error);
				if ($res) {
					fwrite($pipes[0], $res);
				}
				else {
					usleep(1000);
				}
			}
		}
		catch (SignalException $e) {
			if ( ! $e->isSillenceExit()) {
				self::close($pipes);
				throw $e;
			}
		}
		self::close($pipes);
		return proc_close($process);
	}



	/**
	 * Executes the command and returns return code and output.
	 *
	 * @return array array(return code, array with output)
	 */
	function dryRun()
	{
		return $this->buildCommand();
	}



	/**
	 * Append argument.
	 *
	 * @param string
	 * @return self
	 */
	function arg($str)
	{
		$str = trim($str, "\n\t ");
		// first and last char must be '
		if ($str{0} === '\'') {
			$str = escapeshellarg(substr($str, 1, -1));
		}
		$this->arguments[] = $str;
		return $this;
	}



	private static function read($p)
	{
		$ret = '';
		do {
			$chunk = self::CHUNK_SIZE;
			if (isset($unread) && $chunk > $unread) {
				$chunk = $unread;
			}
			$ret .= fread($p, $chunk);
			$unread = self::unread($p);
		} while ($unread);
		return $ret;
	}



	private static function unread($p)
	{
		return stream_get_meta_data($p)['unread_bytes'];
	}



	private static function close(array $pipes)
	{
		foreach ($pipes as $pipe) {
			fclose($pipe);
		}
	}

}
