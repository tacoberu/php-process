<?php
/**
 * @copyright 2016 Martin Takáč (http://martin.takac.name)
 * @license   https://opensource.org/licenses/MIT MIT
 */

namespace Taco\Utils\Process;

use PHPUnit_Framework_TestCase;


class ExecTest extends PHPUnit_Framework_TestCase
{


	function testGenerated()
	{
		$process = new Exec('./success 127.0.0.1 -c 3');
		$this->assertEquals($process->dryRun(), './success 127.0.0.1 -c 3 2>&1');
	}



	function testGeneratedComposeArgument()
	{
		$process = new Exec('./success');
		$process
			->arg('127.0.0.1')
			->arg('-c 3');
		$this->assertEquals($process->dryRun(), './success 127.0.0.1 -c 3 2>&1');
	}



	function testProcessed()
	{
		$process = new Exec('bin/success.php 127.0.0.1 -c 3');
		$process->setWorkDirectory(__dir__);
		$res = $process->run();
		$this->assertEquals(0, $res->code);
		$this->assertEquals([
			'bin/success.php',
			'127.0.0.1',
			'-c',
			'3',
		], $res->content);
	}



	function testProcessedWithError()
	{
		$process = new Exec('bin/fail.php 127.0.0.1 -c 3');
		$process->setWorkDirectory(__dir__);
		try {
			$res = $process->run();
		}
		catch (ExecException $e) {
			$this->assertEquals($e->getCode(), 10);
			$this->assertEquals($e->getMessage(), 'First line
Seccond line
Third line');
			$this->assertEquals($e->getCommand(), 'bin/fail.php 127.0.0.1 -c 3 2>&1');
		}
	}



	function testProcessedEscaped()
	{
		$process = (new Exec('bin/success.php'))
			->arg('First-line')
			->arg('\'Seccond line\'')
			->arg('\'Third \'line\'')
			->arg('\'Four `ls` \'line\'')
			->arg('"Five `echo many` line"');
		$process->setWorkDirectory(__dir__);
		$res = $process->run();
		$this->assertEquals(0, $res->code);
		$this->assertEquals([
			'bin/success.php',
			"First-line",
			"Seccond line",
			'Third \'line',
			'Four `ls` \'line',
			'Five many line',
		], $res->content);
	}



	function testChangeDir()
	{
		$orig = getcwd();
		$process = new Exec('ls');
		$process->setWorkDirectory(__dir__ . '/data');
		$res = $process->run();
		$this->assertEquals(0, $res->code);
		$this->assertEquals($res->content, array('a.txt', 'b.txt', 'c.txt'));
		$this->assertEquals($orig, getcwd());
	}



	function testChangeDirWithError()
	{
		$orig = getcwd();
		$process = new Exec('false');
		$process->setWorkDirectory(__dir__ . '/data');
		try {
			$process->run();
			$this->fail();
		}
		catch (ExecException $e) {}
		$this->assertEquals($orig, getcwd());
	}



	function testChangeDirFail()
	{
		$this->setExpectedException(\RuntimeException::class, 'Directory \'' . __dir__ . '/nope\' is not found.');
		$process = new Exec('ls');
		$process->setWorkDirectory(__dir__ . '/nope');
	}



	function testProcessedWithAgent()
	{
		$process = new Exec(__dir__ . '/bin/readwrite.php');

		// Parametr je funkce zpracovávájící výstup a generující vstup.
		// Pro ilustraci vložíme tři vstupy a ukončíme.
		// Ukončení musí spolupracovat volaná aplikace. A nebo vynutit pomocí ^C. Což uděláme výjimkou.
		$this->bank = ['jenda', 'dva', 'tři', ':q'];
		$outs = new \ArrayObject();
		$process->runAgent(function($out, $err) use ($outs) {
			if ($err) {
				throw new \RuntimeException('error', 10);
			}
			if ($out) {
				$outs[] = $out;
			}
			if ( ! $this->bank) {
				throw SignalException::interrupt();
				//~ throw new SignalException('hmmm', 55);
			}
			$s = array_shift($this->bank) . "\n";
			$outs[] = $s;
			return $s;
		});

		$this->assertEquals([
			'< ',
			"jenda\n",
			"> jenda\n\n< ",
			"dva\n",
			"> dva\n\n< ",
			"tři\n",
			"> tři\n\n< ",
			":q\n",
		], (array) $outs);
	}

}
