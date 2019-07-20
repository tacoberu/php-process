<?php
/**
 * @copyright 2016 Martin Takáč (http://martin.takac.name)
 * @license   https://opensource.org/licenses/MIT MIT
 */

namespace Taco\Utils\Process;


/**
 * Chyba při zpouštění procesu.
 * @author Martin Takáč <martin@takac.name>
 */
class ExecException extends \RuntimeException
{

	/**
	 * Jaký process byl spouštěn.
	 * @var string
	 */
	private $command;


	/**
	 * @var string
	 */
	private $output;


	/**
	 * Předán jako parametr navíc process, který byl spouštěn.
	 */
	function __construct($command, $error, $output, $code = 0, $parent = NULL)
	{
		parent::__construct(!empty($error) ? $error : $output, $code, $parent);
		$this->command = $command;
		$this->output = $output;
	}



	/**
	 * Jaký process byl spouštěn.
	 * @return string
	 */
	function getCommand()
	{
		return $this->command;
	}



	/**
	 * @return string
	 */
	function getOutput()
	{
		return $this->output;
	}



	/**
	 * @return string
	 */
	function getError()
	{
		return $this->getMessage();
	}

}



class SignalException extends \RuntimeException
{
	const SIGNAL_INTERRUPT = 130;
	const SIGNAL_PROCESS_ABORTED = 134;

	static function interrupt()
	{
		return new static('Interrupt', self::SIGNAL_INTERRUPT);
	}


	function isSillenceExit()
	{
		return in_array($this->getCode(), [
			self::SIGNAL_INTERRUPT,
		], True);
	}

}
