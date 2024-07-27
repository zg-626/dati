<?php

namespace app\common\controller;

use app\common\library\Auth;
use app\common\library\base\BaseController;
use app\common\library\MyRedis;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\FFMpeg;
use think\cache\driver\Redis;
use think\Config;
use think\Db;
use think\exception\HttpResponseException;
use think\exception\ValidateException;
use think\Hook;
use think\Lang;
use think\Loader;
use think\Request;
use think\Response;
use think\Route;
use think\Validate;
use app\common\model\Product as ProductModel;
use app\common\model\ManystoreShop as ManystoreShopModel;
use app\common\model\ProductAttrValue as ProductAttrValueModel;

/**
 * API控制器基类
 */
class Api
{
    protected $_page = 1;
    protected $_limit = 20;
    protected $_is_error = 1;//查询信息不存在时 是否直接返回异常
    protected $_is_to_array = 0;//查询信息不存在时 是否直接返回异常
    //返回商家字段
    protected $_shop_field = 'id,name,image,logo,shop_type,shop_text,reply_score';
    //返回商品字段
    protected $_product_field = 'id,product_name,image,price,shop_id';
    /**
     * @var Request Request 实例
     */
    protected $request;

    /**
     * @var bool 验证失败是否抛出异常
     */
    protected $failException = false;

    /**
     * @var bool 是否批量验证
     */
    protected $batchValidate = false;

    /**
     * @var array 前置操作方法列表
     */
    protected $beforeActionList = [];

    /**
     * 无需登录的方法,同时也就不需要鉴权了
     * @var array
     */
    protected $noNeedLogin = [];

    /**
     * 无需鉴权的方法,但需要登录
     * @var array
     */
    protected $noNeedRight = [];

    /**
     * 权限Auth
     * @var Auth
     */
    protected $auth = null;

    /**
     * 默认响应输出类型,支持json/xml
     * @var string
     */
    protected $responseType = 'json';

    /**
     * 构造方法
     * @access public
     * @param Request $request Request 对象
     */
    public function __construct(Request $request = null)
    {
        $this->request = is_null($request) ? Request::instance() : $request;

        // 控制器初始化
        $this->_initialize();

        // 前置操作方法
        if ($this->beforeActionList) {
            foreach ($this->beforeActionList as $method => $options) {
                is_numeric($method) ?
                    $this->beforeAction($options) :
                    $this->beforeAction($method, $options);
            }
        }
    }

    /**
     * 初始化操作
     * @access protected
     */
    protected function _initialize()
    {
        //跨域请求检测
        check_cors_request();

        // 检测IP是否允许
        check_ip_allowed();

        //移除HTML标签
        $this->request->filter('trim,strip_tags,htmlspecialchars');

        $this->auth = Auth::instance();

        $modulename = $this->request->module();
        $controllername = Loader::parseName($this->request->controller());
        $actionname = strtolower($this->request->action());

        // token
        $token = $this->request->server('HTTP_TOKEN', $this->request->request('token', \think\Cookie::get('token')));

        $path = str_replace('.', '/', $controllername) . '/' . $actionname;
        // 设置当前请求的URI
        $this->auth->setRequestUri($path);
        // 检测是否需要验证登录
        if (!$this->auth->match($this->noNeedLogin)) {
            //初始化
            $this->auth->init($token);
            //检测是否登录
            if (!$this->auth->isLogin()) {
                $this->error(__('Please login first'), null, 401);
            }
            // 判断是否需要验证权限
            if (!$this->auth->match($this->noNeedRight)) {
                // 判断控制器和方法判断是否有对应权限
                if (!$this->auth->check($path)) {
                    $this->error(__('You have no permission'), null, 403);
                }
            }
        } else {
            // 如果有传递token才验证是否登录状态
            if ($token) {
                $this->auth->init($token);
            }
        }

        $upload = \app\common\model\Config::upload();

        // 上传信息配置后
        Hook::listen("upload_config_init", $upload);

        Config::set('upload', array_merge(Config::get('upload'), $upload));

        // 加载当前控制器语言包
        $this->loadlang($controllername);
    }

    /**
     * 加载语言文件
     * @param string $name
     */
    protected function loadlang($name)
    {
        $name = Loader::parseName($name);
        Lang::load(APP_PATH . $this->request->module() . '/lang/' . $this->request->langset() . '/' . str_replace('.', '/', $name) . '.php');
    }

    /**
     * 操作成功返回的数据
     * @param string $msg 提示信息
     * @param mixed $data 要返回的数据
     * @param int $code 错误码，默认为1
     * @param string $type 输出类型
     * @param array $header 发送的 Header 信息
     */
    protected function success($msg = '', $data = null, $code = 1, $type = null, array $header = [])
    {
        $this->result($msg, $data, $code, $type, $header);
    }

    /**
     * 操作失败返回的数据
     * @param string $msg 提示信息
     * @param mixed $data 要返回的数据
     * @param int $code 错误码，默认为0
     * @param string $type 输出类型
     * @param array $header 发送的 Header 信息
     */
    protected function error($msg = '', $data = null, $code = 0, $type = null, array $header = [])
    {
        $this->result($msg, $data, $code, $type, $header);
    }

    /**
     * 返回封装后的 API 数据到客户端
     * @access protected
     * @param mixed $msg 提示信息
     * @param mixed $data 要返回的数据
     * @param int $code 错误码，默认为0
     * @param string $type 输出类型，支持json/xml/jsonp
     * @param array $header 发送的 Header 信息
     * @return void
     * @throws HttpResponseException
     */
    protected function result($msg, $data = null, $code = 0, $type = null, array $header = [])
    {
        $result = [
            'code' => $code,
            'msg' => $msg,
            'time' => Request::instance()->server('REQUEST_TIME'),
            'data' => $data,
        ];
        // 如果未设置类型则自动判断
        $type = $type ? $type : ($this->request->param(config('var_jsonp_handler')) ? 'jsonp' : $this->responseType);

        if (isset($header['statuscode'])) {
            $code = $header['statuscode'];
            unset($header['statuscode']);
        } else {
            //未设置状态码,根据code值判断
            $code = $code >= 1000 || $code < 200 ? 200 : $code;
        }
        $response = Response::create($result, $type, $code)->header($header);
        throw new HttpResponseException($response);
    }

    /**
     * 前置操作
     * @access protected
     * @param string $method 前置操作方法名
     * @param array $options 调用参数 ['only'=>[...]] 或者 ['except'=>[...]]
     * @return void
     */
    protected function beforeAction($method, $options = [])
    {
        if (isset($options['only'])) {
            if (is_string($options['only'])) {
                $options['only'] = explode(',', $options['only']);
            }

            if (!in_array($this->request->action(), $options['only'])) {
                return;
            }
        } elseif (isset($options['except'])) {
            if (is_string($options['except'])) {
                $options['except'] = explode(',', $options['except']);
            }

            if (in_array($this->request->action(), $options['except'])) {
                return;
            }
        }

        call_user_func([$this, $method]);
    }

    /**
     * 设置验证失败后是否抛出异常
     * @access protected
     * @param bool $fail 是否抛出异常
     * @return $this
     */
    protected function validateFailException($fail = true)
    {
        $this->failException = $fail;

        return $this;
    }

    /**
     * 验证数据
     * @access protected
     * @param array $data 数据
     * @param string|array $validate 验证器名或者验证规则数组
     * @param array $message 提示信息
     * @param bool $batch 是否批量验证
     * @param mixed $callback 回调方法（闭包）
     * @return array|string|true
     * @throws ValidateException
     */
    protected function validate($data, $validate, $message = [], $batch = false, $callback = null)
    {
        if (is_array($validate)) {
            $v = Loader::validate();
            $v->rule($validate);
        } else {
            // 支持场景
            if (strpos($validate, '.')) {
                list($validate, $scene) = explode('.', $validate);
            }

            $v = Loader::validate($validate);

            !empty($scene) && $v->scene($scene);
        }

        // 批量验证
        if ($batch || $this->batchValidate) {
            $v->batch(true);
        }
        // 设置错误信息
        if (is_array($message)) {
            $v->message($message);
        }
        // 使用回调验证
        if ($callback && is_callable($callback)) {
            call_user_func_array($callback, [$v, &$data]);
        }

        if (!$v->check($data)) {
            if ($this->failException) {
                throw new ValidateException($v->getError());
            }

            return $v->getError();
        }

        return true;
    }

    /**
     * 刷新Token
     */
    protected function token()
    {
        $token = $this->request->param('__token__');

        //验证Token
        if (!Validate::make()->check(['__token__' => $token], ['__token__' => 'require|token'])) {
            $this->error(__('Token verification error'), ['__token__' => $this->request->token()]);
        }

        //刷新Token
        $this->request->token();
    }

    /**
     * 获取分页参数
     * @author xuzhili
     * @date 2022-05-13 14:26
     */
    public function getPage()
    {
        $page = $this->request->param('page');
        $limit = $this->request->param('limit');
        $this->_page = empty($page) ? $this->_page : $page;
        $this->_limit = empty($limit) ? $this->_limit : $limit;
    }

    /**
     * 获取列表
     * @param $model //模型对象
     * @param array $params //固定条件
     * @param array $where //补充条件
     * @param null $time
     * @param null $order
     * @param string $field
     * @return array
     * @author xuzhili
     * @date 2022-05-13 16:48
     */
    public function getList($model, $params = [], $where = [], $time = null, $order = null, $field = '*'): array
    {
        $order = is_null($order) ? 'id desc' : $order;
        $this->getPage();
        $query = $this->search($model, $where, $params, $time);
        $count = $query->count();
        $query2 = $this->search($model, $where, $params, $time, $field);
        $list = $query2->order($order)->page($this->_page, $this->_limit)->select();
        return compact('count', 'list');
    }

    /**
     * 获取列表
     * @param $model
     * @param array $where //检索条件
     * @param array $params //固定条件
     * @param null $time
     * @param string $field
     * @return mixed
     * @author xuzhili
     * @date 2022-05-13 11:51
     */
    public function search($model, $where = [], $params = [], $time = null, $field = '*')
    {
        $query = $model::field($field);
        if (isset($where['type']) && $where['type'] != '') {
            $query->where('type', $where['type']);
        }
        if (isset($where['type_data']) && $where['type_data'] != '') {
            $query->where('type_data', $where['type_data']);
        }

        if (isset($where['in']) && $where['in'] != '') {
            if (!empty($where['in']['field']) && !empty($where['in']['value'])) {
                $query->where($where['in']['field'], 'in', $where['in']['value']);
            }
        }
        if (isset($where['notin']) && $where['notin'] != '') {
            if (!empty($where['notin']['field']) && !empty($where['notin']['value'])) {
                $query->where($where['notin']['field'], 'not in', $where['notin']['value']);
            }
        }

        if (!empty($time) && !empty($time['field']) && !empty($time['value'])) {
            $query->whereTime($time['field'], $time['value']);
        }

        if (isset($where['search']) && $where['search'] != '') {
            if (!empty($where['search']['field']) && !empty($where['search']['value'])) {
                $field_arr = explode(',', $where['search']['field']);
                $value_arr = explode(',', $where['search']['value']);
                $type = $where['search']['type'] ?? 0;
                $spl = $where['search']['spl'] ?? 0;
                $search_arr = [];
                if ($spl == 1){//一对一  字段0 对 值0  字段1 对 值1
                    foreach ($field_arr as $item=>$field) {
                        $search = $this->fuzzy_search($type,$value_arr[$item]);
                        $search_arr[$field] = ['like', $search];
                    }
                }else{//多对多  字段0 对 值0、值1   字段1 对 值0、值1
                    foreach ($field_arr as $field) {
                        foreach ($value_arr as $value) {
                            $search = $this->fuzzy_search($type,$value);
                            $search_arr[$field] = ['like', $search];
                        }
                    }
                }

                $keyword = $where['search']['keyword'] ?? 'whereOr';
                //多条件模糊查询 sql外层需要括号包裹  所以此处使用闭包查询
                $query->where(function ($que) use ($search_arr,$keyword) {
                    $que->$keyword($search_arr);
                });
            }
        }

        if (!empty($params)) {
            $query->where($params);
        }

        return $query;
    }

    /**
     * 返回模糊条件
     * @param $type
     * @param $value
     * @return mixed|string
     * @author xuzhili
     * @date 2022年09月07日 15:34
     */
    private function fuzzy_search($type,$value)
    {
        switch ($type) {
            case 1:
                $search = $value;
                break;
            case 2:
                $search = "%" . $value;
                break;
            default:
                $search = "%" . $value . "%";
                break;
        }
        return $search;
    }

    /**
     * 获取单挑数据
     * @param $model //模型对象
     * @param $where //如果传数组根据数组查询 如果不是数组，根据id查
     * @param array $with
     * @param string $field
     * @param null $order
     * @return mixed
     * @author xuzhili
     * @date 2022-05-13 16:57
     */
    public function getFind($model, $where, $with = [], $field = '*', $order = null)
    {
        $order = is_null($order) ? 'id desc' : $order;
        $where = is_array($where) ? $where : ['id' => $where];
        $res = $model::getDB()->field($field)->where($where)->with($with)->order($order)->find();
        if (empty($res) && $this->_is_error) $this->error('信息异常');
        return $res;
    }

    /**
     * 更新数控
     * @param $model
     * @param $where //如果传数组根据数组更新 如果不是数组，根据id查
     * @param $data //更新数据
     * @return mixed
     * @author xuzhili
     * @date 2022-05-19 13:45
     */
    public function updateData($model, $where, $data)
    {
        $where = is_array($where) ? $where : ['id' => $where];
        $res = $model::getInstance()->where($where)->update($data);
        if (!$res && $this->_is_error) $this->error('更新失败');
        return $res;
    }

    /**
     * 删除数据
     * @param $model
     * @param array $where
     * @author xuzhili
     * @date 2022-05-20 16:35
     */
    public function delete($model, $where)
    {
        $where = is_array($where) ? $where : ['id' => $where];
        return $model::getInstance()->where($where)->delete();
    }

    /**
     * 判断数据是否存在
     * @param $model
     * @param $where //如果传数组根据数组更新 如果不是数组，根据id查
     * @return bool
     * @author xuzhili
     * @date 2022-05-19 18:06
     */
    public function isDataExits($model, $where)
    {
        $where = is_array($where) ? $where : ['id' => $where];
        $res = $model::getInstance()->where($where)->count();
        return $res > 0;
    }

    /**
     * 获取条数
     * @param $model //模型对象
     * @param array $params //固定条件
     * @param array $where //补充条件
     * @param null $time //时间条件
     * @return int
     * @author xuzhili
     * @date 2022年06月16日 14:27
     */
    public function getCount($model, array $params = [], array $where = [], $time = null): int
    {
        $query = $this->search($model,$where, $params);
        if (!empty($time)) $query->whereTime($time['field'],$time['value']);
        return $query->count();
    }

    /**
     * 校验参数是否存在
     * @param array $data
     * @param array $field
     * @param string $msg //错误提示信息
     * @author xuzhili
     * @date 2022-05-13 18:48
     */
    public function check_params(array $data, array $field, $msg = '参数错误')
    {
        if (!is_array($field)) $this->error('参数错误');
        foreach ($field as $value) {
            if (!isset($data[$value]) || $data[$value] == '') {
                $this->error($msg);
            }
        }
        if (!empty($data['mobile'])) {
            $this->check_phone($data['mobile']);
        }
        if (!empty($data['phone'])) {
            $this->check_phone($data['phone']);
        }
    }

    /**
     * 判断数据是否存在 存在则返回
     * @param array $field //需要校验的字段
     * @param array $data //校验数据
     * @param $res //返回的信息 传数组或对象
     * @param int $type //res参数的类型 1数组 否则对象
     * @author xuzhili
     * @date 2022年06月27日 9:46
     */
    public function check_data_exits(array $field, array $data, $res = [], $type = 1)
    {
        foreach ($field as $item => $value) {
            if (isset($data[$value]) && $data[$value] != '') {
                if ($type == 1) {
                    $res[$value] = $data[$value];
                } else {
                    $res->$value = $data[$value];
                }
            }
        }
        return $res;
    }

    /**
     * 手机号校验
     * @param $phone
     * @return bool
     * @author xuzhili
     * @date 2022年06月11日 9:45
     */
    public function check_phone($phone)
    {
        if (!preg_match('/^1[345789]\d{9}$/ims', $phone)) {
            $this->error(__('手机号格式错误'));
        }
        return true;
    }

    /**
     * 组装数据
     * @param $data
     * @param array $del_data //需要从数组中去掉的参数
     * @return array
     * @author xuzhili
     * @date 2022-05-19 14:16
     */
    public function params_data($data, $del_data = [])
    {
        foreach ($data as $item => $value) {
            if (!empty($del_data) && in_array($item, $del_data)) {
                continue;
            }
            if (isset($value) && $value != '') {
                $res[$item] = $value;
            }
        }
        if (empty($res)) $this->error('不存在更新数据');
        return $res;
    }


    /**
     * 校验信息是否存在
     * @param $data //数据
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author xuzhili
     * @date 2022-06-01 10:14
     */
    public function check_type_exits($data)
    {
        foreach ($data as $item => $value) {
            if ($item == 'shop_id') { //判断商家是否存在
                $model = ManystoreShopModel::class;
                $error_msg = '商家信息不存在';
            } else if ($item == 'product_id') {//判断商品是否存在
                $model = ProductModel::class;
                $error_msg = '商品信息不存在';
            } else if ($item == 'product_attr_value_id') { //判断商品属性值是否存在
                $model = ProductAttrValueModel::class;
                $error_msg = '商品属性值信息不存在';
            } else {
                $this->error('校验参数错误');
            }
            $res = $this->isDataExits($model, $value);
            if (!$res) $this->error($error_msg);
        }
    }

    /**
     * 使用redis存储数据
     * @param $key
     * @param $value
     * @param float|int $expire_time
     * @author xuzhili
     * @date 2022-06-06 13:54
     */
    public function setRedis($key, $value, $expire_time = 60 * 60)
    {
        $redis = new MyRedis();
        $redis->setex(config('site.redis_prefix') . $key, $expire_time, $value);
    }

    /**
     * 从redis中取出数据
     * @param $key
     * @return mixed|null
     * @author xuzhili
     * @date 2022-06-06 13:54
     */
    public function getRedis($key)
    {
        $redis = new \Redis();
        if ($redis->has($key)) {
            return $redis->get($key);
        }
        return null;
    }

    /**
     * 返回商家字段
     * @return string
     * @author xuzhili
     * @date 2022年06月08日 17:09
     */
    public function getShopField()
    {
        return $this->_shop_field;
    }

    /**
     * 返回商品字段
     * @return string
     * @author xuzhili
     * @date 2022年06月08日 17:09
     */
    public function getProductField()
    {
        return $this->_product_field;
    }

    /**
     * 获取视频封面图
     * @param $path //视频地址
     * @return string
     * @author xuzhili
     * @date 2022年07月15日 16:18
     */
    public function getVideoImg($path): string
    {
        $ffmpeg = FFMpeg::create(array(
            //程序安装目录，不加可能会无法运行
            'ffmpeg.binaries' => '/usr/local/bin/ffmpeg',
            'ffprobe.binaries' => '/usr/local/bin/ffprobe'
        ));
        $video = $ffmpeg->open($path);
        $frame = $video->frame(TimeCode::fromSeconds(1));//获取第几帧
        $filename = time() . ".jpg";//获取图片命名
        $frame->save($filename);//获取图片

        $dirname = date("Ymd");//设置日期文件夹
        if (!is_dir("/uploads/$dirname")) {//是否已有文件夹
            mkdir("/uploads/$dirname");//没有则新建文件夹
        }
        $img_path = "/uploads/$dirname/";
        copy($filename, $img_path); //拷贝到新目录
        return $img_path . $filename;
    }

    public function screenshot($path, $outPath)
    {
        $shell = "ffmpeg -i " . $path . " -ss 1 -y -frames:v 1 -q:v 1 " . $outPath . " 2>&1";
        exec($shell, $output, $ret);
        return $res;
    }


}
