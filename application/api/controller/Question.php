<?php

namespace app\api\controller;

use app\common\controller\Api;
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
    protected $noNeedLogin = ['list'];
    // 无需鉴权的接口,*表示全部
    protected $noNeedRight = ['*'];

    /**
     * 问答列表
     * @return array
     */
    public function list(): ?array
    {
        $search = $this->request->param('search');
        $where = [];
        if (!empty($search)){
            $where['search'] = ['field' => 'name', 'value' => $search];
        }
        $this->success('success', $this->getList(QuestionModel::class,[], $where, null, null, 'id,title,createtime,status'));
    }

    /**
     * 我的问答列表
     * @return array
     */
    public function mylist(): ?array
    {
        $search = $this->request->param('search');
        $where = [];
        if (!empty($search)){
            $where['search'] = ['field' => 'name', 'value' => $search];
        }
        $where['user_id'] = $this->auth->id;
        $this->success('success', $this->getList(QuestionModel::class, $where));
    }

    /**
     * 发布问答
     *
     */
    public function add()
    {
        $data = input('post.');
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
        $data = input('post.');
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
        $this->success('success', $this->getFind(QuestionModel::class, $id));
    }

}
