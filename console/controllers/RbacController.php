<?php 

namespace console\controllers;

use Yii;
use  yii\console\Controller;

class RbacController extends Controller
{
	public function actionInit()
	{
		$auth = \Yii::$app->authManager;
		
		// 添加 "craetePost" 权限
		$createPost = $auth->createPermission('createPost');
		$createPost->description = '新增文章';
		$auth->add($createPost);
		
		// 添加 "updatePost" 权限
		$updatePost = $auth->createPermission('updatePost');
		$updatePost->description = '修改文章';
		$auth->add($updatePost);
		
		// 添加 "deletePost" 权限
		$deletePost = $auth->createPermission('deletePost');
		$deletePost->description = '删除文章';
		$auth->add($deletePost);
		
		// 添加 "approveComment" 权限
		$approveComment = $auth->createPermission('approveComment');
		$approveComment->description = '审核评论';
		$auth->add($approveComment);
		
		// 添加 "postAdmin" 角色并赋予 'createPost' 'updatePost' "deletePost" 权限
		$postAdmin = $auth->createRole('postAdmin');
		$auth->add($postAdmin);
		$auth->addChild($postAdmin, $createPost);
		$auth->addChild($postAdmin, $updatePost);
		$auth->addChild($postAdmin, $deletePost);
		
		// 添加 "postOperator" 角色并赋予 "deletePost" 权限
		$postOperator = $auth->createRole('postOperator');
		$auth->add($postOperator);
		$auth->addChild($postOperator, $deletePost);
		
		// 添加 "commentAuditor" 角色并赋予 "approveComment" 权限
		$commentAuditor = $auth->createRole('commentAuditor');
		$auth->add($commentAuditor);
		$auth->addChild($commentAuditor, $approveComment);
		
		// 添加 "admin" 角色并赋予 所有其他角色拥有的权限
		$admin = $auth->createRole('admin');
		$auth->add($admin);
		$auth->addChild($admin, $postAdmin);
		$auth->addChild($admin, $commentAuditor);
		
		// 为用户指派角色。 其中 1  和 2 是由 IdentityInterface::getId() 返回的 id 
		// 通常在你的 User 模型中实现这个函数
		$auth->assign($admin, 1);
		$auth->assign($postAdmin, 2);
		$auth->assign($postOperator, 3);
		$auth->assign($commentAuditor, 4);
		
	}
}