define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'user/user/index',
                    add_url: 'user/user/add',
                    edit_url: 'user/user/edit',
                    del_url: 'user/user/del',
                    import_url: 'user/user/import',
                    multi_url: 'user/user/multi',
                    table: 'user',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'user.id',
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id'), sortable: true,operate: false},
                        //{field: 'group.name', title: __('Group')},
                        //{field: 'hospital.name', title: __('Hospital_name')},
                        //{field: 'username', title: __('Username'), operate: 'LIKE'},
                        {field: 'nickname', title: __('Nickname'), operate: 'LIKE'},
                        //{field: 'gender', title: __('Gender'),formatter: Table.api.formatter.status,operate: false, searchList: {1: __('Male'), 0: __('Female')}},
                        {field: 'mobile', title: __('Mobile'), operate: 'LIKE'},
                        {field: 'is_vip', title: __('会员开关'), operate: false, formatter:Table.api.formatter.toggle},
                        //{field: 'email', title: __('Email'), operate: 'LIKE'},
                        //{field: 'avatar', title: __('Avatar'), events: Table.api.events.image, formatter: Table.api.formatter.image, operate: false},
                        //{field: 'level', title: __('Level'), operate: 'BETWEEN', sortable: true},
                         //{field: 'score', title: __('Score'), operate: 'BETWEEN', sortable: true},
                        //{field: 'successions', title: __('Successions'), visible: false, operate: 'BETWEEN', sortable: true},
                        //{field: 'maxsuccessions', title: __('Maxsuccessions'), visible: false, operate: 'BETWEEN', sortable: true},
                        //{field: 'logintime', title: __('Logintime'), formatter: Table.api.formatter.datetime, operate: 'RANGE', addclass: 'datetimerange', sortable: true},
                        //{field: 'loginip', title: __('Loginip'), formatter: Table.api.formatter.search},
                        {field: 'jointime', title: __('Jointime'), formatter: Table.api.formatter.datetime, addclass: 'datetimerange', sortable: true},
                        //{field: 'joinip', title: __('Joinip'), formatter: Table.api.formatter.search},
                        //{field: 'status', title: __('Status'), formatter: Table.api.formatter.status, searchList: {normal: '已审核', hidden: '驳回','none':'未审核'}},
                        {field: 'is_vip', title: '会员', formatter: Table.api.formatter.status, searchList: {0: '非会员', 1: '是会员'}},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate,
                            buttons: [
                                /*{
                                    name: 'addtabs',
                                    title: '查看',
                                    text: '查看',
                                    classname: 'btn btn-xs btn-warning btn-addtabs',
                                    icon: 'fa fa-list',
                                    url: 'example/bootstraptable/detail'
                                },*/
                                {
                                    name: 'edit',
                                    text: '编辑',
                                    icon: 'fa fa-pencil',
                                    title: __('Edit'),
                                    extend: 'data-toggle="tooltip"',
                                    classname: 'btn btn-xs btn-success btn-editone'
                                }
                            ], formatter: Table.api.formatter.operate}
                    ]
                ]
            });
            // 列表打印
            $(document).on("click", ".btn-start,.btn-pause", function () {
                //在table外不可以使用添加.btn-change的方法
                //只能自己调用Table.api.multi实现
                //如果操作全部则ids可以置为空
                var ids = Table.api.selectedids(table);
                // 直接调用原生的打印
                window.print();
            });
            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        add: function () {
            Controller.api.bindevent();
        },
        edit: function () {
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            },
            formatter: {//渲染的方法
                is_vip: function (value, row, index) {
                    //添加上btn-change可以自定义请求的URL进行数据处理
                    return '<a class="btn-change text-success" data-url="user/user/change" data-confirm="确认切换会员状态？" data-id="' + row.id + '"><i class="fa ' + (row.title == '' ? 'fa-toggle-on fa-flip-horizontal text-gray' : 'fa-toggle-on') + ' fa-2x"></i></a>';
                },
            },
        }
    };
    return Controller;
});