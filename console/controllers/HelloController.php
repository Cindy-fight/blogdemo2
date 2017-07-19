<?php

namespace console\controllers;

use yii\console\Controller;
use common\models\Post;

class HelloController extends Controller
{
	public $rev;
	
	public function options($actionID)
	{
		return ['rev'];
	}
	
	public function optionAliases()
	{
		return ['r' => 'rev'];
	}
	
	public function actionIndex()
	{
		if ($this->rev == 1)
		{
			echo strrev('Hello World!') . "\n";
		}else {
			echo 'Hello World!' . "\n";
		}
	}
	
	public function actionList() 
	{
		$posts = Post::find()->all();
		foreach ($posts as $post)
		{
			echo $post['id'] . '_' . $post['title'] . "\n";
		}
	}
	
	public function actionWho($name)
	{
		echo ('Hello ' . $name . "!\n");
	}
	
	public function actionBoth($name, $anthor)
	{
		echo ('Hello ' . $name . ' and ' . $anthor . "!\n");
	}
	
	public function actionAll(array $names)
	{
		var_dump($names);
	}
	
}