<?php

use yii\helpers\Html;
use yii\grid\GridView;
use common\models\Commentstatus;

/* @var $this yii\web\View */
/* @var $searchModel common\models\CommentSearch */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = '评论管理';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="comment-index">

    <h1><?= Html::encode($this->title) ?></h1>
    <?php // echo $this->render('_search', ['model' => $searchModel]); ?>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
//             ['class' => 'yii\grid\SerialColumn'],

//             'id',
			['attribute' => 'id',
			 'contentOptions' => ['width' => '30'],
			],
        		
//             'content:ntext',
			['attribute' => 'content',
			 'value' => 'beginning',
			],
        	['attribute' => 'user.username',
        	'label' => '作者',
        	'value' => 'user.username',
        	'contentOptions' => ['width' => '100'],
        	],
        		
//             'status',
        	['attribute' => 'status',
        	 'value' => 'status0.name',
        	 'filter' => Commentstatus::find()
        			->select(['name', 'id'])
        			->orderBy('position')
        			->indexBy('id')
        			->column(),
        	'contentOptions' => 
        			function ($model)
        			{
        				return ($model->status == 1) ? ['class' => 'bg-danger'] : [];
        			}
        			
    		],
//             'create_time:datetime',
			['attribute' => 'create_time',
			 'format' => ['date', 'php:Y-m-d H:i:s'],
        	],
//             'userid',
			
            // 'email:email',
            // 'url:url',
            // 'post_id',
//             'post.title',
  			['attribute' => 'post.title',
  			 'label' => '标题',
  			 'value' => 'post.title',
        	],

//             ['class' => 'yii\grid\ActionColumn'],
    		[
    			'class' => 'yii\grid\ActionColumn',
    			'template' => '{view} {update} {delete} {approve}',
    			'buttons' => 
    				[
    					'approve' => function ($url,$model,$key)
    						{
								$options = [
										'title' => Yii::t('yii', '审核'),
										'aria-label' => Yii::t('yii', '审核'),
										'data-confirm' => Yii::t('yii', '你确定通过这条评论吗？'),
										'data-method' => 'post',
										'data-pjax' => 0,
								];
								return \yii\bootstrap\Html::a('<span class = "glyphicon glyphicon-check"></span>', $url, $options);
							}
					],
    				
			],
        ],
    ]); ?>
</div>
