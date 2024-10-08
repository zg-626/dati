define(['jquery', 'bootstrap', 'backend', 'table', 'form', 'template'], function ($, undefined, Backend, Table, Form, Template) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'example/bootstraptable/index',
                    add_url: '',
                    edit_url: 'example/bootstraptable/edit',
                    del_url: 'example/bootstraptable/del',
                    multi_url: '',
                }
            });

            var table = $("#table");

            //在普通搜索提交搜索前
            table.on('common-search.bs.table', function (event, table, query) {
                //这里可以获取到普通搜索表单中字段的查询条件
                console.log(query);
            });

            //在普通搜索渲染后
            table.on('post-common-search.bs.table', function (event, table) {
                var form = $("form", table.$commonsearch);
                $("input[name='title']", form).addClass("selectpage").data("source", "example/bootstraptable/selectpage").data("primaryKey", "title").data("field", "title").data("orderBy", "id desc");
                $("input[name='username']", form).addClass("selectpage").data("source", "auth/admin/index").data("primaryKey", "username").data("field", "username").data("orderBy", "id desc");
                Form.events.cxselect(form);
                Form.events.selectpage(form);
            });

            //在表格内容渲染完成后回调的事件
            table.on('post-body.bs.table', function (e, settings, json, xhr) {
                console.log(e, settings, json, xhr);
            });

            //当表格数据加载完成时
            table.on('load-success.bs.table', function (e, data) {
                //这里可以获取从服务端获取的JSON数据
                console.log(data);
                //这里我们手动设置底部的值
                $("#money").text(data.extend.money);
                $("#price").text(data.extend.price);
            });

            // 初始化表格
            // 这里使用的是Bootstrap-table插件渲染表格
            // 相关文档：https://doc.fastadmin.net/doc/table.html
            table.bootstrapTable({
                //表格参数可以参考：https://doc.fastadmin.net/doc/190.html
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                columns: [
                    [
                        //更多列参数可以参考：https://doc.fastadmin.net/doc/191.html
                        //该列为复选框字段,如果后台的返回state值将会默认选中
                        {field: 'state', checkbox: true,},
                        //sortable为是否排序,operate为搜索时的操作符,visible表示是否可见
                        {field: 'id', title: 'ID', sortable: true, operate: false},
                        //直接响应搜索
                        {field: 'username', title: __('管理员'), formatter: Table.api.formatter.search},
                        //模糊搜索
                        {field: 'title', title: __('Title'), operate: 'LIKE %...%', placeholder: '模糊搜索，*表示任意字符', width: '280px'},
                        //通过Ajax渲染searchList，也可以使用JSON数据
                        {
                            field: 'url',
                            title: __('Url'),
                            align: 'left',
                            searchList: $.getJSON('example/bootstraptable/searchlist?search=a&field=row[user_id]'),
                            formatter: Controller.api.formatter.url,
                            addClass: "selectpicker"
                        },
                        //点击IP时同时执行搜索此IP
                        {
                            field: 'ip',
                            title: __('IP'),
                            events: Controller.api.events.ip,
                            formatter: Controller.api.formatter.ip
                        },
                        //自定义栏位,custom是不存在的字段
                        {field: 'custom', title: __('切换'), operate: false, formatter: Controller.api.formatter.custom},
                        {
                            field: 'admin_id', title: __('联动搜索'), searchList: function (column) {
                                return Template('categorytpl', {});
                            }, formatter: function (value, row, index) {
                                return '无';
                            }, visible: false
                        },
                        //启用时间段搜索
                        {
                            field: 'createtime',
                            title: __('Update time'),
                            sortable: true,
                            formatter: Table.api.formatter.datetime,
                            operate: 'RANGE',
                            addclass: 'datetimerange'
                        },
                        //操作栏,默认有编辑、删除或排序按钮,可自定义配置buttons来扩展按钮
                        {
                            field: 'operate',
                            width: "150px",
                            title: __('Operate'),
                            table: table,
                            events: Table.api.events.operate,
                            buttons: [
                                {
                                    name: 'click',
                                    title: __('点击执行事件'),
                                    classname: 'btn btn-xs btn-info btn-click',
                                    icon: 'fa fa-leaf',
                                    // dropdown: '更多',//如果包含dropdown，将会以下拉列表的形式展示
                                    click: function (data) {
                                        Layer.alert("点击按钮执行的事件");
                                    }
                                },
                                {
                                    name: 'detail',
                                    title: __('弹出窗口打开'),
                                    classname: 'btn btn-xs btn-primary btn-dialog',
                                    icon: 'fa fa-list',
                                    url: 'example/bootstraptable/detail',
                                    callback: function (data) {
                                        Layer.alert("接收到回传数据：" + JSON.stringify(data), {title: "回传数据"});
                                    }
                                },
                                {
                                    name: 'ajax',
                                    title: __('发送Ajax'),
                                    classname: 'btn btn-xs btn-success btn-magic btn-ajax',
                                    icon: 'fa fa-magic',
                                    confirm: '确认发送Ajax请求？',
                                    url: 'example/bootstraptable/detail',
                                    success: function (data, ret) {
                                        Layer.alert(ret.msg + ",返回数据：" + JSON.stringify(data));
                                        //如果需要阻止成功提示，则必须使用return false;
                                        //return false;
                                    },
                                    error: function (data, ret) {
                                        console.log(data, ret);
                                        Layer.alert(ret.msg);
                                        return false;
                                    }
                                },
                                {
                                    name: 'addtabs',
                                    title: __('新选项卡中打开'),
                                    classname: 'btn btn-xs btn-warning btn-addtabs',
                                    icon: 'fa fa-folder-o',
                                    url: 'example/bootstraptable/detail'
                                }
                            ],
                            formatter: Table.api.formatter.operate
                        },
                    ],
                ],
                //更多配置参数可参考：https://doc.fastadmin.net/doc/190.html
                //亦可以参考require-table.js中defaults的配置
                //快捷搜索,这里可在控制器定义快捷搜索的字段
                search: true,
                //启用普通表单搜索
                commonSearch: true,
                //显示导出按钮
                showExport: true,
                //启用跨页选择
                maintainSelected: true,
                //启用固定列
                fixedColumns: true,
                //固定左侧列数
                fixedNumber: 3,
                //固定右侧列数
                fixedRightNumber: 1,
                //导出类型
                exportDataType: "all", //共有basic, all, selected三种值 basic当前页 all全部 selected仅选中
                //导出下拉列表选项
                exportTypes: ['json', 'xml', 'csv', 'txt', 'doc', 'excel'],
                //可以控制是否默认显示搜索单表,false则隐藏,默认为false
                searchFormVisible: true,
                queryParams: function (params) {
                    //这里可以追加搜索条件
                    var filter = JSON.parse(params.filter);
                    var op = JSON.parse(params.op);
                    //这里可以动态赋值，比如从URL中获取admin_id的值，filter.admin_id=Fast.api.query('admin_id');
                    filter.admin_id = 1;
                    op.admin_id = "=";
                    params.filter = JSON.stringify(filter);
                    params.op = JSON.stringify(op);
                    return params;
                },
            });

            // 为表格绑定事件
            Table.api.bindevent(table);

            // 监听下拉列表改变的事件
            $(document).on('change', 'select[name=admin]', function () {
                $("input[name='admin_id']").val($(this).val());
            });

            //自定义Tab筛选条件
            $('.panel-heading .nav-custom-condition a[data-toggle="tab"]', table.closest(".panel-intro")).on('shown.bs.tab', function (e) {
                var value = $(this).data("value");
                var options = table.bootstrapTable('getOptions');
                var queryParams = options.queryParams;
                options.pageNumber = 1;
                options.queryParams = function (params) {
                    //这一行必须要存在,否则在点击下一页时会丢失搜索栏数据
                    params = queryParams(params);

                    //如果希望追加搜索条件,可使用
                    var filter = params.filter ? JSON.parse(params.filter) : {};
                    var op = params.op ? JSON.parse(params.op) : {};
                    if (value) {
                        //这里可以自定义多个筛选条件
                        filter.admin_id = value;
                        op.admin_id = '=';
                    } else {
                        //选全部时要移除相应的条件
                        delete filter.admin_id;
                        delete op.admin_id;
                    }

                    params.filter = JSON.stringify(filter);
                    params.op = JSON.stringify(op);

                    //如果希望忽略搜索栏搜索条件,可使用
                    //params.filter = JSON.stringify(value?{admin_id: value}:{});
                    //params.op = JSON.stringify(value?{admin_id: '='}:{});
                    return params;
                };

                table.trigger("uncheckbox");
                table.bootstrapTable('refresh', {pageNumber: 1});
                return false;
            });

            // 指定搜索条件
            $(document).on("click", ".btn-singlesearch", function () {
                var options = table.bootstrapTable('getOptions');
                var queryParams = options.queryParams;
                options.pageNumber = 1;
                options.queryParams = function (params) {
                    //这一行必须要存在,否则在点击下一页时会丢失搜索栏数据
                    params = queryParams(params);

                    //如果希望追加搜索条件,可使用
                    var filter = params.filter ? JSON.parse(params.filter) : {};
                    var op = params.op ? JSON.parse(params.op) : {};
                    filter.url = 'login';
                    op.url = 'like';

                    params.filter = JSON.stringify(filter);
                    params.op = JSON.stringify(op);

                    //如果希望忽略搜索栏搜索条件,可使用
                    //params.filter = JSON.stringify({url: 'login'});
                    //params.op = JSON.stringify({url: 'like'});
                    return params;
                };
                table.bootstrapTable('refresh', {});
                Toastr.info("当前执行的是自定义搜索,搜索URL中包含login的数据");
                return false;
            });

            // 获取选中项
            $(document).on("click", ".btn-selected", function () {
                Layer.alert(JSON.stringify(Table.api.selecteddata(table)));
            });

            // 启动和暂停按钮
            $(document).on("click", ".btn-start,.btn-pause", function () {
                //在table外不可以使用添加.btn-change的方法
                //只能自己调用Table.api.multi实现
                //如果操作全部则ids可以置为空
                var ids = Table.api.selectedids(table);
                Table.api.multi("changestatus", ids.join(","), table, this);
            });

        },
        add: function () {
            Controller.api.bindevent();
        },
        edit: function () {
            Controller.api.bindevent();
        },
        detail: function () {
            $(document).on('click', '.btn-callback', function () {
                Fast.api.close($("input[name=callback]").val());
            });
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            },
            formatter: {//渲染的方法
                url: function (value, row, index) {
                    return '<div class="input-group input-group-sm" style="width:250px;"><input type="text" class="form-control input-sm" value="' + value + '"><span class="input-group-btn input-group-sm"><a href="' + value + '" target="_blank" class="btn btn-default btn-sm"><i class="fa fa-link"></i></a></span></div>';
                },
                ip: function (value, row, index) {
                    return '<a class="btn btn-xs btn-ip bg-success"><i class="fa fa-map-marker"></i> ' + value + '</a>';
                },
                custom: function (value, row, index) {
                    //添加上btn-change可以自定义请求的URL进行数据处理
                    return '<a class="btn-change text-success" data-url="example/bootstraptable/change" data-confirm="确认切换状态？" data-id="' + row.id + '"><i class="fa ' + (row.title == '' ? 'fa-toggle-on fa-flip-horizontal text-gray' : 'fa-toggle-on') + ' fa-2x"></i></a>';
                },
            },
            events: {//绑定事件的方法
                ip: {
                    //格式为：方法名+空格+DOM元素
                    'click .btn-ip': function (e, value, row, index) {
                        e.stopPropagation();
                        var container = $("#table").data("bootstrap.table").$container;
                        var options = $("#table").bootstrapTable('getOptions');
                        //这里我们手动将数据填充到表单然后提交
                        $("form.form-commonsearch [name='ip']", container).val(value);
                        $("form.form-commonsearch", container).trigger('submit');
                        Toastr.info("执行了自定义搜索操作");
                    }
                },
            }
        }
    };
    return Controller;
});
