<?php

class QuickstartActiveButtonTestCase extends PradoDemosSelenium2Test
{
	public function test()
	{
		$this->url("quickstart/index.php?page=ActiveControls.Samples.TActiveButton.Home&amp;notheme=true&amp;lang=en");

		$this->assertEquals("PRADO QuickStart Sample", $this->title());

		$this->assertSourceContains('TActiveButton Samples (AJAX)');

		// a click button
		$this->assertElementNotPresent("//input[@type='submit' and @value=\"I'm clicked\"]");
		$this->byXPath("//input[@type='submit' and @value='click me']")->click();
		$this->pauseFairAmount();
		$this->assertElementPresent("//input[@type='submit' and @value=\"I'm clicked\"]");

		// a command button
		$this->assertElementNotPresent("//input[@type='submit' and @value=\"Name: test, Param: value using callback\"]");
		$this->byXPath("//input[@type='submit' and @value='click me']")->click();
		$this->pauseFairAmount();
		$this->assertElementPresent("//input[@type='submit' and @value=\"Name: test, Param: value using callback\"]");

		// a button causing validation
		$this->assertNotVisible('ctl0_body_ctl2');
		$this->byXPath("//input[@type='submit' and @value='submit']")->click();
		$this->pauseFairAmount();
		$this->assertVisible('ctl0_body_ctl2');
		$this->type("ctl0\$body\$TextBox", "test");
		$this->byXPath("//input[@type='submit' and @value='submit']")->click();
		$this->pauseFairAmount();
		$this->assertNotVisible('ctl0_body_ctl2');
		$this->assertElementPresent("//input[@type='submit' and @value=\"I'm clicked using callback\"]", "");
	}
}
