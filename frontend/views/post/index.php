<?php

 
use yii\widgets\ListView;
use yii\base\Widget;
use frontend\components\TagsCloudWidget;
use frontend\components\RctReplyWidget;
use yii\helpers\Html;
use common\models\Post;
use common\library\Util;

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
			<div class = "searchbox">
				<ul class = "list-group">
					<li class = "list-group-item">
						<span class = "glyphicon glyphicon-search" aria-hidden = "true"></span>&nbsp;&nbsp;查找文章
						
						<?php 
// 						$data = Yii::$app->cache->get('Counts');
// 						if (!$data){
// 							$count = Post::find()->where(['status' => 2])->count();
// 							Yii::$app->cache->set('Counts', $count);
// 						}
// 						echo '( ' . $data . ' )';
// 						?>
						
						<?php 
						$data = Util::fetchCache('Counts');
						if (!$data){
							$count = Post::find()->where(['status' => 2])->count();
							Util::setCache('Counts', $count);
						}
						echo '( ' . $data . ' )';
						?>
						
					</li>
					<li class = "list-group-item">
						<form class="form-inline" action = "index.php?r=post/index" id = "w0" method = "get" >
							  <div class="form-group">
							    <input type="text" class="form-control" name="PostSearch[title]" id="w0input" placeholder="按标题">
							  </div>
						  	  <button type="submit" class="btn btn-default">搜索</button>
						</form>
					</li>
				</ul>
			</div>
			
			<div class = "tagcloudbox">
				<ul class = "list-group">
					<li class = "list-group-item">
						<span class = "glyphicon glyphicon-tags" aria-hidden = "true"></span>&nbsp;&nbsp;标签云
					</li>
					<li class = "list-group-item">
						<?= TagsCloudWidget::widget(['tags' => $tags]); ?>
					</li>
				</ul>
			</div>
			
			<div class = "commentbox">
				<ul class = "list-group">
					<li class = "list-group-item">
						<span class = "glyphicon glyphicon-comment" aria-hidden = "true"></span>&nbsp;&nbsp;最新回复
					</li>
					<li class = "list-group-item">
						<?= RctReplyWidget::widget(['recentComments' => $recentComments]); ?>
					</li>
				</ul>
			</div>
			
		</div>
		
	</div>
	
</div>
