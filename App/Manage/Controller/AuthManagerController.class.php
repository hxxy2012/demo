<?php
// +----------------------------------------------------------------------
// | OneThink [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013 http://www.onethink.cn All rights reserved.
// +----------------------------------------------------------------------
// | Author: 朱亚杰 <zhuyajie@topthink.net>
// +----------------------------------------------------------------------

namespace Manage\Controller;
use Manage\Model\AuthRuleModel;
use Manage\Model\AuthGroupModel;

/**
 * 权限管理控制器
 * Class AuthManagerController
 * @author 朱亚杰 <zhuyajie@topthink.net>
 */
class AuthManagerController extends AdminController{

    protected function _initialize()
    {
        parent::_initialize(); // TODO: Change the autogenerated stub
        $this->group_title = '管理员管理';
    }

    /**
     * 后台节点配置的url作为规则存入auth_rule
     * 执行新节点的插入,已有节点的更新,无效规则的删除三项任务
     * @author 朱亚杰 <zhuyajie@topthink.net>
     */
    public function updateRules(){
        //需要新增的节点必然位于$nodes
        $nodes    = $this->returnNodes(false);

        $AuthRule = M('AuthRule');
        $map      = array('module'=>'admin','type'=>array('in','1,2'));//status全部取出,以进行更新
        //需要更新和删除的节点必然位于$rules
        $rules    = $AuthRule->where($map)->order('name')->select();

        //构建insert数据
        $data     = array();//保存需要插入和更新的新节点
        foreach ($nodes as $value){
            $temp['name']   = count(explode('/',$value['url'])) == 2 ? MODULE_NAME.'/'.$value['url'] : $value['url'];
            $temp['title']  = $value['title'];
            $temp['module'] = 'admin';
            $temp['id'] = $value['id'];
            if($value['pid'] >0){
                $temp['type'] = AuthRuleModel::RULE_URL;
            }else{
                $temp['type'] = AuthRuleModel::RULE_MAIN;
            }
            $temp['status']   = 1;
            $data[strtolower($temp['name'].$temp['module'].$temp['type'])] = $temp;//去除重复项
        }

        $update = array();//保存需要更新的节点
        $ids    = array();//保存需要删除的节点的id
        foreach ($rules as $index=>$rule){
            $key = strtolower($rule['name'].$rule['module'].$rule['type']);
            if ( isset($data[$key]) ) {//如果数据库中的规则与配置的节点匹配,说明是需要更新的节点
                $data[$key]['rid'] = $rule['rid'];//为需要更新的节点补充id值
                $update[] = $data[$key];
                unset($data[$key]);
                unset($rules[$index]);
                unset($rule['condition']);
                $diff[$rule['id']]=$rule;
            }elseif($rule['status']==1){
                $ids[] = $rule['id'];
            }
        }
        
        if ( count($update) ) {
            foreach ($update as $k=>$row){
                if ( $row!=$diff[$row['id']] )
                    $AuthRule->where(array('rid'=>$row['rid']))->save($row);
            }
        }
        if ( count($ids) ) {
            $AuthRule->where(array( 'id'=>array('IN',implode(',',$ids)) ))->delete();
            //删除规则是否需要从每个用户组的访问授权表中移除该规则?
        }
        if( count($data) ){
            $AuthRule->addAll(array_values($data));
        }
        if ( $AuthRule->getDbError() ) {
            trace('['.__METHOD__.']:'.$AuthRule->getDbError());
            return false;
        }else{
            return true;
        }
    }


    /**
     * 权限管理首页
     * @author 朱亚杰 <zhuyajie@topthink.net>
     */
    public function index(){
        $now_page = I('get.page', 1);
        $page_size = 15;
        $map = array(
            'module'    =>  'admin',
            'status'    =>  array('egt',0),
        );
        $count = M('AuthGroup')->where($map)->count();
        $list = M('AuthGroup')->where($map)->page($now_page,$page_size)->order('id asc')->select();
        $pages = intval(ceil($count / $page_size));
        $list = array(
            'list'  =>  $list,
            'pages' =>  $pages,
            'get'   =>  $_GET,
        );
        $this->list = $list;
        //$this->_use_tip = true;
        $this->meta_title = '分组管理';
        $this->display();
    }

    /**
     * 创建管理员用户组
     * @author 朱亚杰 <zhuyajie@topthink.net>
     */
    public function createGroup(){
        if ( empty($this->auth_group) ) {
            $this->assign('auth_group',array('title'=>null,'id'=>null,'description'=>null,'rules'=>null,));//排除notice信息
        }
        $this->meta_title = '新增用户组';
        $this->display('editgroup');
    }

    /**
     * 编辑管理员用户组
     * @author 朱亚杰 <zhuyajie@topthink.net>
     */
    public function editGroup(){
        $auth_group = M('AuthGroup')->where( array('module'=>'admin','type'=>AuthGroupModel::TYPE_ADMIN) )
                                    ->find( (int)$_GET['id'] );
        $this->assign('auth_group',$auth_group);
        $this->meta_title = '编辑用户组';
        $this->display();
    }


    /**
     * 访问授权页面
     * @author 朱亚杰 <zhuyajie@topthink.net>
     */
    public function access(){
        $this->updateRules();
        $map =  array('status'=>array('egt','0'), 'module'=>'admin', 'type'=>AuthGroupModel::TYPE_ADMIN,'id'=>I('get.group_id'));
        $this_group = M('AuthGroup')->where($map)->getfield('id,title,rules');
        $node_list   = $this->returnNodes();
        /*$map         = array('module'=>'admin','type'=>AuthRuleModel::RULE_MAIN,'status'=>1);
        $main_rules  = M('AuthRule')->where($map)->getField('name,id');*/
        /*$map         = array('module'=>'admin','type'=>AuthRuleModel::RULE_URL,'status'=>1);
        $child_rules = M('AuthRule')->where($map)->getField('name,id');*/

        $this->node_list = $node_list;
        $this->this_group = $this_group[intval(I('get.group_id'))];
        $this->meta_title = '访问授权';
        $this->display('managergroup');
    }

    /**
     * 管理员用户组数据写入/更新
     * @author 朱亚杰 <zhuyajie@topthink.net>
     */
    public function writeGroup(){
        if(isset($_POST['rules'])){
            sort($_POST['rules']);
            $_POST['rules']  = implode( ',' , array_unique($_POST['rules']));
        }else
            $_POST['rules'] = '';
        $_POST['module'] =  'admin';
        $_POST['type']   =  AuthGroupModel::TYPE_ADMIN;
        $AuthGroup       =  D('AuthGroup');
        $data = $AuthGroup->create();
        if ( $data ) {
            if ( empty($data['id']) ) {
                $r = $AuthGroup->add();
            }else{
                $r = $AuthGroup->save();
            }
            if($r===false){
                $this->error('操作失败'.$AuthGroup->getError(),'',true);
            } else{
                $this->success('操作成功!',U('index'),'',true);
            }
        }else{
            $this->error('操作失败'.$AuthGroup->getError());
        }
    }

    /**
     * 状态修改
     * @author 朱亚杰 <zhuyajie@topthink.net>
     */
    public function changeStatus($method=null){
        if ( empty($_REQUEST['id']) )
            $this->error('请选择要操作的数据!');
        switch ( strtolower($method) ){
            case 'close':
                $this->forbid('AuthGroup');
                break;
            case 'open':
                $this->resume('AuthGroup');
                break;
            case 'del':
                $this->delete('AuthGroup');
                break;
            default:
                $this->error($method.'参数非法');
        }
    }

    /**
     * 用户组授权用户列表
     * @author 朱亚杰 <zhuyajie@topthink.net>
     */
    public function user($group_id){
        if(empty($group_id)){
            $this->error('参数错误');
        }
        $now_page = I('get.page', 1);
        $page_size = 15;

        $auth_group = M('AuthGroup')->where( array('status'=>array('egt','0'),'module'=>'admin','type'=>AuthGroupModel::TYPE_ADMIN) )
            ->getfield('id,id,title,rules');

        $Model = M(AuthGroupModel::MEMBER.' as m');
        $map = array('a.group_id'=>$group_id,'m.status'=>array('egt',0));
        $count = $Model->join('left join __'.strtoupper(AuthGroupModel::AUTH_GROUP_ACCESS).'__ as a on m.uid = a.uid')->where($map)->count();
        $list = $Model
            ->join('left join __'.strtoupper(AuthGroupModel::AUTH_GROUP_ACCESS).'__ as a on m.uid = a.uid')
            ->order('m.uid asc')
            ->field('m.uid,m.nickname,m.last_login_time,m.last_login_ip,m.status')
            ->where($map)
            ->page($now_page,$page_size)
            ->select();
        $pages = intval(ceil($count / $page_size));
        //int_to_string($list);
        $list = array(
            'list'  =>  $list,
            'pages' =>  $pages,
            'get'   =>  $_GET,
        );
        $this->list = $list;
        $this->auth_group = $auth_group;
        $this->this_group = $auth_group[(int)$_GET['group_id']];
        /*$this->assign( '_list',     $list );
        $this->assign('auth_group', $auth_group);
        $this->assign('this_group', $auth_group[(int)$_GET['group_id']]);*/
        $this->meta_title = '成员授权';
        $this->display();
    }

    /**
     * 将分类添加到用户组的编辑页面
     * @author 朱亚杰 <zhuyajie@topthink.net>
     */
    public function category(){
        $auth_group     =   M('AuthGroup')->where( array('status'=>array('egt','0'),'module'=>'admin','type'=>AuthGroupModel::TYPE_ADMIN) )
            ->getfield('id,id,title,rules');
        $group_list     =   D('Category')->getTree();
        $authed_group   =   AuthGroupModel::getCategoryOfGroup(I('group_id'));
        $this->assign('authed_group',   implode(',',(array)$authed_group));
        $this->assign('group_list',     $group_list);
        $this->assign('auth_group',     $auth_group);
        $this->assign('this_group',     $auth_group[(int)$_GET['group_id']]);
        $this->meta_title = '分类授权';
        $this->display();
    }

    public function tree($tree = null){
        $this->assign('tree', $tree);
        $this->display('tree');
    }

    /**
     * 将用户添加到用户组的编辑页面
     * @author 朱亚杰 <zhuyajie@topthink.net>
     */
    public function group(){
        $uid            =   I('uid');
        $auth_groups    =   D('AuthGroup')->getGroups();
        $user_groups    =   AuthGroupModel::getUserGroup($uid);
        $ids = array();
        foreach ($user_groups as $value){
            $ids[]      =   $value['group_id'];
        }
        $this->nickname =   get_nickname($uid);
        $this->auth_groups = $auth_groups;
        $this->user_groups = implode(',',$ids);
        $this->meta_title = '用户组授权';
        $this->display();
    }

    /**
     * 将用户添加到用户组,入参uid,group_id
     * @author 朱亚杰 <zhuyajie@topthink.net>
     */
    public function addToGroup(){
        $uid = I('uid');
        $gid = I('group_id');
        if( empty($uid) ){
            $this->error('参数有误');
        }
        $AuthGroup = D('AuthGroup');
        if(is_numeric($uid)){
            if ( is_administrator($uid) ) {
                $this->error('该用户为超级管理员');
            }
            if( !M('manage_member')->where(array('uid'=>$uid))->find() ){
                $this->error('用户不存在');
            }
        }

        if( $gid && !$AuthGroup->checkGroupId($gid)){
            $this->error($AuthGroup->error);
        }
        if ( $AuthGroup->addToGroup($uid,$gid) ){
            $this->success('操作成功');
        }else{
            $this->error($AuthGroup->getError());
        }
    }

    /**
     * 将用户从用户组中移除  入参:uid,group_id
     * @author 朱亚杰 <zhuyajie@topthink.net>
     */
    public function removeFromGroup(){
        $uid = I('uid');
        $gid = I('group_id');
        if( $uid==UID ){
            $this->error('不允许解除自身授权');
        }
        if( empty($uid) || empty($gid) ){
            $this->error('参数有误');
        }
        $AuthGroup = D('AuthGroup');
        if( !$AuthGroup->find($gid)){
            $this->error('用户组不存在');
        }
        if ( $AuthGroup->removeFromGroup($uid,$gid) ){
            $this->success('操作成功');
        }else{
            $this->error('操作失败');
        }
    }

    /**
     * 将分类添加到用户组  入参:cid,group_id
     * @author 朱亚杰 <zhuyajie@topthink.net>
     */
    public function addToCategory(){
        $cid = I('cid');
        $gid = I('group_id');
        if( empty($gid) ){
            $this->error('参数有误');
        }
        $AuthGroup = D('AuthGroup');
        if( !$AuthGroup->find($gid)){
            $this->error('用户组不存在');
        }
        if( $cid && !$AuthGroup->checkCategoryId($cid)){
            $this->error($AuthGroup->error);
        }
        if ( $AuthGroup->addToCategory($gid,$cid) ){
            $this->success('操作成功');
        }else{
            $this->error('操作失败');
        }
    }

    /**
     * 将模型添加到用户组  入参:mid,group_id
     * @author 朱亚杰 <xcoolcc@gmail.com>
     */
    public function addToModel(){
        $mid = I('id');
        $gid = I('get.group_id');
        if( empty($gid) ){
            $this->error('参数有误');
        }
        $AuthGroup = D('AuthGroup');
        if( !$AuthGroup->find($gid)){
            $this->error('用户组不存在');
        }
        if( $mid && !$AuthGroup->checkModelId($mid)){
            $this->error($AuthGroup->error);
        }
        if ( $AuthGroup->addToModel($gid,$mid) ){
            $this->success('操作成功');
        }else{
            $this->error('操作失败');
        }
    }
}
