<?php
$I = new UiTester($scenario);
$I->wantTo('check ask page');
$I->amOnPage('/questions/ask/');
$I->makeScreenshot(ap_screenshot_inc() . 'ask');

$I->fillField([ 'name' => 'form_question[post_title]' ], 'Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas.');
$I->fillTinyMceEditorById('form_question-post_content', 'Nulla vestibulum ultricies neque eu semper. Phasellus hendrerit ullamcorper est eget tincidunt.');
$I->selectOption('form_question[category]', 'Sample Cat');
//$I->fillField([ 'css' => '#form_question-tags-selectized' ], 'testTag1,testTag2,testtag3');
$I->click('//*[@id="form_question"]/button');
$I->waitForJS("return jQuery.active == 0;", 30);
$i->seeElement('#question');
