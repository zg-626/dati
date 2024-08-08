<?php

namespace app\api\controller;

use app\common\controller\Api;
use app\common\model\Banner;
use app\common\model\Category;
use app\common\model\Question as QuestionModel;
/**
 * 问答接口
 */
class Question extends Api
{

    //如果$noNeedLogin为空表示所有接口都需要登录才能请求
    //如果$noNeedRight为空表示所有接口都需要验证权限才能请求
    //如果接口已经设置无需登录,那也就无需鉴权了
    //
    // 无需登录的接口,*表示全部
    protected $noNeedLogin = ['list','getCate','getBanner'];
    // 无需鉴权的接口,*表示全部
    protected $noNeedRight = ['*'];

    /**
     * 问答列表
     * @return array
     */
    public function list(): ?array
    {
        $search = $this->request->param('search');
        $cid = $this->request->param('cid');
        $where = [];
        $params = [];
        if (!empty($search)){
            $where['search'] = ['field' => 'title', 'value' => $search];
        }
        if(!empty($cid)){
            $params['cid'] = $cid;
        }
        $list=$this->getList(QuestionModel::class,$params, $where, null, null, 'id,user_id,admin_id,title,content,reply,createtime,updatetime,status');
        $with=[
            'people'=>function($query){
                $query->field('id,username,is_vip');
            },
            'teacher'=>function($query){
                $query->field('id,username');
            }
        ];
        foreach ($list['list'] as $k => $v) {
            $list['list'][$k]['createtime'] =date('Y-m-d H:i:s', $v['createtime']);
            $list['list'][$k]['updatetime'] =date('Y-m-d H:i:s', $v['updatetime']);
        }
        load_relation($list['list'], $with);
        $this->success('success', $list);
    }

    /**
     * 我的问答列表
     * @return array
     */
    public function mylist(): ?array
    {
        $user = $this->auth->getUser();
        $search = $this->request->param('search');
        $cid = $this->request->param('cid');
        $where = [];
        $params = [];
        if (!empty($search)){
            $where['search'] = ['field' => 'title', 'value' => $search];
        }
        if(!empty($cid)){
            $params['cid'] = $cid;
        }
        $params['user_id'] = $user->id;
        $list=$this->getList(QuestionModel::class,$params, $where, null, null, 'id,user_id,admin_id,title,content,reply,createtime,updatetime,status');
        $with=[
            'people'=>function($query){
                $query->field('id,username,is_vip');
            },
            'teacher'=>function($query){
                $query->field('id,username');
            }
        ];
        foreach ($list['list'] as $k => $v) {
            $list['list'][$k]['createtime'] =date('Y-m-d H:i:s', $v['createtime']);
            $list['list'][$k]['updatetime'] =date('Y-m-d H:i:s', $v['updatetime']);
        }
        load_relation($list['list'], $with);
        $this->success('success', $list);
    }

    /**
     * 分类列表
     *
     */
    public function getCate()
    {
        $where['status'] = 'normal';
        $order = ['weigh' => 'desc', 'id' => 'desc'];
        $list = $this->getList(Category::class,[], $where, null, $order, 'id,name,image,type,flag');
        //统计各栏目问题数量
        foreach ($list['list'] as $k => $v) {
            $list['list'][$k]['count'] = $this->getCount(QuestionModel::class, ['cid' => $v['id']]);
        }
        $this->success('success', $list);

    }

    /**
     * 分类列表
     *
     */
    public function getBanner()
    {
        $where['status'] = 'normal';
        $list = $this->getList(Banner::class,[], $where, null, null, 'id,image');
        $this->success('success', $list);

    }

    /**
     * 发布问答
     *
     */
    public function add()
    {
        $data = $this->request->post(false);
        $this->check_params($data, ['title', 'content', 'cid']);
        $data['user_id'] = $this->auth->id;
        $res = QuestionModel::getInstance()->create($data);
        $this->success('添加成功', $res);
    }

    /**
     * 编辑问答
     *
     */
    public function edit()
    {
        $data = $this->request->post(false);
        $this->check_params($data, ['id']);
        $res = $this->params_data($data, ['id']);

        $this->success('修改成功', $this->updateData(QuestionModel::class, $data['id'], $res));
    }

    /**
     * 问答详情
     *
     */
    public function detail($id = 0)
    {
        if (empty($id)) $this->error('参数错误');
        $info = $this->getFind(QuestionModel::class, $id);
        $with=[
            'people'=>function($query){
                $query->field('id,username,is_vip');
            },
            'teacher'=>function($query){
                $query->field('id,username');
            }
        ];

        load_relation($info, $with);
        $info['createtime'] =date('Y-m-d H:i:s', $info['createtime']);
        $info['updatetime'] =date('Y-m-d H:i:s', $info['updatetime']);
        $this->success('success', $info);
    }

}
