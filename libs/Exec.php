<?php
/**
 * @copyright 2016 Martin Takáč (http://martin.takac.name)
 * @license   https://opensource.org/licenses/MIT MIT
 */

namespace Taco\Utils\Process;


/**
 * $res = (new Process\Exec('ping 127.0.0.1 -c 3'))->run();
 *
 * @author Martin Takáč <martin@takac.name>
 */
class Exec
{

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
	protected function buildCommand()
	{
		$args = $this->arguments;
		$args[] = '2>&1';
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

}
