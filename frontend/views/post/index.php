<?php

 
use yii\widgets\ListView;
use yii\base\Widget;

/* @var $this yii\web\View */
/* @var $searchModel common\models\PostSearch */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = '文章列表';
$this->params['breadcrumbs'][] = $this->title;
?>

<div class = "container">
	
	<div class = "row">
		
		<div class = "col-md-9">
			<?= ListView::widget([
					'id' => 'postlist',
					'dataProvider' => $dataProvider,
					'itemView' => '_listitem', //子视图 显示一篇文章的标题等内容
					'layout' => '{items} {pager}',
					'pager' => [
							'maxButtonCount' => 5,
							'nextPageLabel' => Yii::t('app', '下一页'),
							'prevPageLabel' => Yii::t('app', '上一页'),
			],
			])?>
		</div>
		
		<div class = "col-md-3">
			右侧内容
		</div>
		
	</div>
	
</div>
