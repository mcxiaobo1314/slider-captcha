<?php
/**
 * 资产负债表配置
 * @author  wave
 */

return  [
    'asset' => [ //资产
        [
            'no' => '',
            'name' => '流动资产：',
            'default_show' => true, //默认显示文案，无任何数据
        ],
        [
            'no' => 1,
            'name' => '货币资金',
            'special_add_sub' => true, //特殊相加减
            'add' => [
                'default' => [
                    [
                        'subject_code' => '1001',
                        'subject_name' => '库存现金',  //科目名称
                        'subject_type' => 1, //科目类型
                        'loan_type' => 1, //余额方向 借贷
                    ],
                    [
                        'subject_code' => '1002',
                        'subject_name' => '银行存款',  //科目名称
                        'subject_type' => 1, //科目类型
                        'loan_type' => 1, //余额方向 借贷
                    ],
                    [
                        'subject_code' => '1012',
                        'subject_name' => '其他货币资金',  //科目名称
                        'subject_type' => 1, //科目类型
                        'loan_type' => 1, //余额方向 借贷
                    ]
                ]
            ],
        ],
        [
            'no' => 2,
            'name' => '短期投资',
            'special_add_sub' => true, //特殊相加减
            'add' => [
                'default' => [
                    [
                        'subject_code' => '1101',
                        'subject_name' => '短期投资',
                        'subject_type' => 1,
                        'loan_type' => 1,
                    ]
                ]
            ]
        ],
        [
            'no' => 3,
            'name' => '应收票据',
            'special_add_sub' => true, //特殊相加减
            'add' => [
                'default' => [
                    [
                        'subject_code' => '1121',
                        'subject_name' => '应收票据',
                        'subject_type' => 1,
                        'loan_type' => 1,
                    ]
                ]
            ]
        ],
        [
            'no' => 4,
            'name' => '应收账款', //贷方则加到预付账款
            'special_add_sub' => true, //特殊相加减
            'add' => [
                'debit' => [
                    [
                        'subject_code' => '1122',
                        'subject_name' => '应收账款',
                        'subject_type' => 1,
                        'loan_type' => 1
                    ],
                    [
                        'subject_code' => '2203',
                        'subject_name' => '预收账款',
                        'subject_type' => 2,
                        'loan_type' => 2
                    ]
                ]
            ],
        ],
        [
            'no' => 5,
            'name' => '预付账款', //贷方则加到应收账款
            'special_add_sub' => true, //特殊相加减
            'add'  => [
                'debit' => [
                    [
                        'subject_code' => '1123',
                        'subject_name' => '预付账款',
                        'subject_type' => 1,
                        'loan_type' => 1
                    ],
                    [
                        'subject_code' => '2202',
                        'subject_name' => '应付账款',
                        'subject_type' => 2,
                        'loan_type' => 2
                    ]
                ]
            ]
        ],
        [
            'no' => 6,
            'name' => '应收股利',
            'special_add_sub' => true, //特殊相加减
            'add' => [
                'default' =>
                [
                    [
                        'subject_code' => '1131',
                        'subject_name' => '应收股利',
                        'subject_type' => 1,
                        'loan_type' => 1
                    ]
                ]
            ]
        ],
        [
            'no' => 7,
            'name' => '应收利息',
            'special_add_sub' => true, //特殊相加减
            'add' => [
                'default' =>
                [
                    [
                        'subject_code' => '1132',
                        'subject_name' => '应收利息',
                        'subject_type' => 1,
                        'loan_type' => 1
                    ]
                ]
            ]
        ],
        [
            'no' => 8,
            'name' => '其他应收款',
            'special_add_sub' => true, //特殊相加减
            'add' => [
                'default' =>
                [
                    [
                        'subject_code' => '1221',
                        'subject_name' => '其他应收款',
                        'subject_type' => 1,
                        'loan_type' => 1
                    ]
                ]
            ]
        ],
        [
            'no' => 9,
            'name' => '存货',
            'special_add_sub' => true, //特殊相加减
            'add' => [
                'default' => [
                    [
                        'subject_code' => '1401',
                        'subject_name' => '材料采购',
                        'subject_type' => 1,
                        'loan_type' => 1
                    ],
                    [
                        'subject_code' => '1402',
                        'subject_name' => '在途物资',
                        'subject_type' => 1,
                        'loan_type' => 1
                    ],
                    [
                        'subject_code' => '1403',
                        'subject_name' => '原材料',
                        'subject_type' => 1,
                        'loan_type' => 1
                    ],
                    [
                        'subject_code' => '1405',
                        'subject_name' => '库存商品',
                        'subject_type' => 1,
                        'loan_type' => 1
                    ],
                    [
                        'subject_code' => '1408',
                        'subject_name' => '委托加工物资',
                        'subject_type' => 1,
                        'loan_type' => 1
                    ],
                    [
                        'subject_code' => '1411',
                        'subject_name' => '周转材料',
                        'subject_type' => 1,
                        'loan_type' => 1
                    ],
                    [
                        'subject_code' => '1421',
                        'subject_name' => '消耗性生物资产',
                        'subject_type' => 1,
                        'loan_type' => 1
                    ],
                ],
                'accounting_system' => [
                    1 => [
                        [
                            'subject_code' => '4001',
                            'subject_name' => '生产成本',
                            'subject_type' => 4,
                            'loan_type' => 2
                        ],
                    ],
                    2 => [
                        [
                            'subject_code' => '5001',
                            'subject_name' => '生产成本',
                            'subject_type' => 5,
                            'loan_type' => 1
                        ]
                    ]
                ],
                'debit' => [
                    [
                        'subject_code' => '1404',
                        'subject_name' => '材料成本差异',
                        'subject_type' => 1,
                        'loan_type' => 1
                    ]
                ]
            ],
            'sub' => [
                'credit' => [
                    [
                        'subject_code' => '1407',
                        'subject_name' => '商品进销差价',
                        'subject_type' => 1,
                        'loan_type' => 2
                    ],
                    [
                        'subject_code' => '1404',
                        'subject_name' => '材料成本差异',
                        'subject_type' => 1,
                        'loan_type' => 1
                    ]
                ],
            ],
        ],
        [
            'no' => 10,
            'name' => '其中：原材料',
            'special_add_sub' => true, //特殊相加减
            'add' => [
                'default' => [
                    [
                        'subject_code' => '1403',
                        'subject_name' => '原材料',
                        'subject_type' => 1,
                        'loan_type' => 1
                    ]
                ]
            ]
        ],
        [
            'no' => 11,
            'name' => '在产品',
            'special_add_sub' => true, //特殊相加减
            'add' => [
                'accounting_system' => [ //key 是会计制度
                    1 => [[
                        'subject_code' => '4001',
                        'subject_name' => '生产成本',
                        'subject_type' => 4,
                        'loan_type' => 1
                    ]],
                    2 => [[
                        'subject_code' => '5001',
                        'subject_name' => '生产成本',
                        'subject_type' => 5,
                        'loan_type' => 1
                    ]],
                ]
            ]
        ],
        [
            'no' => 12,
            'name' => '库存商品',
            'special_add_sub' => true, //特殊相加减
            'add' => [
                'default' => [
                    [
                        'subject_code' => '1405',
                        'subject_name' => '库存商品',
                        'subject_type' => 1,
                        'loan_type' => 1
                    ]
                ]
            ],
        ],
        [
            'no' => 13,
            'name' => '周转材料',
            'special_add_sub' => true, //特殊相加减
            'add' => [
                'default' => [
                    [
                        'subject_code' => '1411',
                        'subject_name' => '周转材料',
                        'subject_type' => 1,
                        'loan_type' => 1
                    ]
                ]
            ],
        ],
        [
            'no' => 14,
            'name' => '其他流动资产',
            'default_show' => true, //默认显示文案，暂时空着
        ],
        [
            'no' => 15,
            'name' => '流动资产合计',
            'sum_no_range' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 14],
            'default_show' => true, //默认显示文案，暂时空着
        ],
        [
            'no' => '',
            'name' => '非流动资产：',
            'default_show' => true, //默认显示文案，无任何数据
        ],
        [
            'no' => 16,
            'name' => '长期债券投资',
            'special_add_sub' => true, //特殊相加减
            'add' => [
                'default' => [
                    [
                        'subject_code' => '1501',
                        'subject_name' => '长期债券投资',
                        'subject_type' => 1,
                        'loan_type' => 1
                    ]
                ]
            ],
        ],
        [
            'no' => 17,
            'name' => '长期股权投资',
            'special_add_sub' => true, //特殊相加减
            'add' => [
                'default' => [
                    [
                        'subject_code' => '1511',
                        'subject_name' => '长期股权投资',
                        'subject_type' => 1,
                        'loan_type' => 1
                    ]
                ]
            ],
        ],
        [
            'no' => 18,
            'name' => '固定资产原价',
            'special_add_sub' => true, //特殊相加减
            'add' => [
                'default' => [
                    [
                        'subject_code' => '1601',
                        'subject_name' => '固定资产',
                        'subject_type' => 1,
                        'loan_type' => 1
                    ]
                ]
            ],
        ],
        [
            'no' => 19,
            'name' => '减：累计折旧',
            'special_add_sub' => true, //特殊相加减
            'add' => [
                'default' => [
                    [
                        'subject_code' => '1602',
                        'subject_name' => '累计折旧',
                        'subject_type' => 1,
                        'loan_type' => 2
                    ]
                ]
            ],
        ],
        [
            'no' => 20,
            'name' => '固定资产账面价值',
            'sub_no_range' => [18, 19],
            'default_show' => true, //默认显示文案，无任何数据
        ],
        [
            'no' => 21,
            'name' => '在建工程',
            'special_add_sub' => true, //特殊相加减
            'add' => [
                'default' => [
                    [
                        'subject_code' => '1604',
                        'subject_name' => '在建工程',
                        'subject_type' => 1,
                        'loan_type' => 1
                    ]
                ]
            ],
        ],
        [
            'no' => 22,
            'name' => '工程物资',
            'special_add_sub' => true, //特殊相加减
            'add' => [
                'default' => [
                    [
                        'subject_code' => '1605',
                        'subject_name' => '工程物资',
                        'subject_type' => 1,
                        'loan_type' => 1
                    ]
                ]
            ],
        ],
        [
            'no' => 23,
            'name' => '固定资产清理',
            'special_add_sub' => true, //特殊相加减
            'add' => [
                'default' => [
                    [
                        'subject_code' => '1606',
                        'subject_name' => '固定资产清理',
                        'subject_type' => 1,
                        'loan_type' => 1
                    ]
                ]
            ],
        ],
        [
            'no' => 24,
            'name' => '生产性生物资产',
            'special_add_sub' => true, //特殊相加减
            'add' => [
                'default' => [
                    [
                        'subject_code' => '1621',
                        'subject_name' => '生产性生物资产',
                        'subject_type' => 1,
                        'loan_type' => 1
                    ]
                ]
            ],
            'sub' => [
                'default' => [
                    [
                        'subject_code' => '1622',
                        'subject_name' => '生产性生物资产累计折旧',
                        'subject_type' => 1,
                        'loan_type' => 2
                    ]
                ],
            ]
        ],
        [
            'no' => 25,
            'name' => '无形资产',
            'special_add_sub' => true, //特殊相加减
            'add' => [
                'default' => [
                    [
                        'subject_code' => '1701',
                        'subject_name' => '无形资产',
                        'subject_type' => 1,
                        'loan_type' => 1
                    ]
                ]
            ],
            'sub' => [
                'default' => [
                    [
                        'subject_code' => '1702',
                        'subject_name' => '累计摊销',
                        'subject_type' => 1,
                        'loan_type' => 2
                    ]
                ],
            ]
        ],
        [
            'no' => 26,
            'name' => '开发支出',
            'special_add_sub' => true, //特殊相加减
            'add' => [
                'accounting_system' => [
                    1 => [
                        [
                            'subject_code' => '4301',
                            'subject_name' => '研发支出',
                            'subject_type' => 4,
                            'loan_type' => 1
                        ],
                    ],
                    2 => [
                        [
                            'subject_code' => '5301',
                            'subject_name' => '研发支出',
                            'subject_type' => 5,
                            'loan_type' => 1
                        ]
                    ]
                ],
            ],
        ],
        [
            'no' => 27,
            'name' => '长期待摊费用',
            'special_add_sub' => true, //特殊相加减
            'add' => [
                'default' => [
                    [
                        'subject_code' => '1801',
                        'subject_name' => '长期待摊费用',
                        'subject_type' => 1,
                        'loan_type' => 1
                    ]
                ]
            ],
        ],
        [
            'no' => 28,
            'name' => '其他非流动资产',
            'default_show' => true, //默认显示文案，无任何数据
        ],
        [
            'no' => 29,
            'name' => '非流动资产合计',
            'default_show' => true, //默认显示文案，无任何数据
            'sum_no_range' => [16, 17, 20, 21, 22, 23, 24, 25, 26, 27, 28],

        ],
        [
            'no' => 30,
            'name' => '资产合计',
            'default_show' => true, //默认显示文案，无任何数据
            'sum_no_range' => [15, 29],
        ],
    ],
    'equity' => [ //负债
        [
            'no' => '',
            'name' => '流动负债：',
            'default_show' => true, //默认显示文案，无任何数据
        ],
        [
            'no' => 31,
            'name' => '短期借款',
            'special_add_sub' => true, //特殊相加减
            'add' => [
                'default' => [
                    [
                        'subject_code' => '2001',
                        'subject_name' => '短期借款',
                        'subject_type' => 2,
                        'loan_type' => 2
                    ]
                ]
            ],
        ],
        [
            'no' => 32,
            'name' => '应付票据',
            'special_add_sub' => true, //特殊相加减
            'add' => [
                'default' => [
                    [
                        'subject_code' => '2201',
                        'subject_name' => '应付票据',
                        'subject_type' => 2,
                        'loan_type' => 2
                    ]
                ]
            ],
        ],
        [
            'no' => 33,
            'name' => '应付账款',
            'special_add_sub' => true, //特殊相加减
            'add'  => [
                'credit' => [
                    [
                        'subject_code' => '1123',
                        'subject_name' => '预付账款',
                        'subject_type' => 1,
                        'loan_type' => 1
                    ],
                    [
                        'subject_code' => '2202',
                        'subject_name' => '应付账款',
                        'subject_type' => 2,
                        'loan_type' => 2
                    ]
                ]
            ]
        ],
        [
            'no' => 34,
            'name' => '预收账款',
            'special_add_sub' => true, //特殊相加减
            'add' => [
                'credit' => [
                    [
                        'subject_code' => '1122',
                        'subject_name' => '应收账款',
                        'subject_type' => 1,
                        'loan_type' => 1
                    ],
                    [
                        'subject_code' => '2203',
                        'subject_name' => '预收账款',
                        'subject_type' => 2,
                        'loan_type' => 2
                    ]
                ]
            ],
        ],
        [
            'no' => 35,
            'name' => '应付职工薪酬',
            'special_add_sub' => true, //特殊相加减
            'add' => [
                'default' => [
                    [
                        'subject_code' => '2211',
                        'subject_name' => '应付职工薪酬',
                        'subject_type' => 2,
                        'loan_type' => 2
                    ]
                ]
            ],
        ],
        [
            'no' => 36,
            'name' => '应交税费',
            'special_add_sub' => true, //特殊相加减
            'add' => [
                'default' => [
                    [
                        'subject_code' => '2221',
                        'subject_name' => '应交税费',
                        'subject_type' => 2,
                        'loan_type' => 2
                    ]
                ]
            ],
        ],
        [
            'no' => 37,
            'name' => '应付利息',
            'special_add_sub' => true, //特殊相加减
            'add' => [
                'default' => [
                    [
                        'subject_code' => '2231',
                        'subject_name' => '应付利息',
                        'subject_type' => 2,
                        'loan_type' => 2
                    ]
                ]
            ],
        ],
        [
            'no' => 38,
            'name' => '应付利润',
            'special_add_sub' => true, //特殊相加减
            'add' => [
                'default' => [
                    [
                        'subject_code' => '2232',
                        'subject_name' => '应付利润',
                        'subject_type' => 2,
                        'loan_type' => 2
                    ]
                ]
            ],
        ],
        [
            'no' => 39,
            'name' => '其他应付款',
            'special_add_sub' => true, //特殊相加减
            'add' => [
                'default' => [
                    [
                        'subject_code' => '2241',
                        'subject_name' => '其他应付款',
                        'subject_type' => 2,
                        'loan_type' => 2
                    ]
                ]
            ],
        ],
        [
            'no' => 40,
            'name' => '其他流动负债',
            'default_show' => true, //默认显示文案，无任何数据
        ],
        [
            'no' => 41,
            'name' => '流动负债合计',
            'sum_no_range' => [31, 32, 33, 34, 35, 36, 37, 38, 39, 40],
            'default_show' => true, //默认显示文案，无任何数据
        ],
        [
            'no' => '',
            'name' => '非流动负债：',
            'default_show' => true, //默认显示文案，无任何数据
        ],
        [
            'no' => 42,
            'name' => '长期借款',
            'special_add_sub' => true, //特殊相加减
            'add' => [
                'default' => [
                    [
                        'subject_code' => '2501',
                        'subject_name' => '长期借款',
                        'subject_type' => 2,
                        'loan_type' => 2
                    ]
                ]
            ],
        ],
        [
            'no' => 43,
            'name' => '长期应付款',
            'special_add_sub' => true, //特殊相加减
            'add' => [
                'default' => [
                    [
                        'subject_code' => '2701',
                        'subject_name' => '长期应付款',
                        'subject_type' => 2,
                        'loan_type' => 2
                    ]
                ]
            ],
        ],
        [
            'no' => 44,
            'name' => '递延收益',
            'special_add_sub' => true, //特殊相加减
            'add' => [
                'default' => [
                    [
                        'subject_code' => '2401',
                        'subject_name' => '递延收益',
                        'subject_type' => 2,
                        'loan_type' => 2
                    ]
                ]
            ],
        ],
        [
            'no' => 45,
            'name' => '其他非流动负债',
            'default_show' => true, //默认显示文案，无任何数据

        ],
        [
            'no' => 46,
            'name' => '非流动负债合计',
            'sum_no_range' => [42, 43, 44, 45],
            'default_show' => true, //默认显示文案，无任何数据

        ],
        [
            'no' => 47,
            'name' => '负债合计',
            'sum_no_range' => [41, 46],
            'default_show' => true, //默认显示文案，无任何数据
        ],
        [
            'no' => '',
            'name' => '',
            'default_show' => true, //默认显示文案，无任何数据

        ],
        [
            'no' => '',
            'name' => '',
            'default_show' => true, //默认显示文案，无任何数据

        ],
        [
            'no' => '',
            'name' => '',
            'default_show' => true, //默认显示文案，无任何数据

        ],
        [
            'no' => '',
            'name' => '',
            'default_show' => true, //默认显示文案，无任何数据

        ],
        [
            'no' => '',
            'name' => '',
            'default_show' => true, //默认显示文案，无任何数据

        ],
        [
            'no' => '',
            'name' => '',
            'default_show' => true, //默认显示文案，无任何数据

        ],
        [
            'no' => '',
            'name' => '所有者权益（或股东权益）：',
            'default_show' => true, //默认显示文案，无任何数据

        ],
        [
            'no' => 48,
            'name' => '实收资本（或股本）',
            'special_add_sub' => true, //特殊相加减
            'add' => [
                'accounting_system' => [
                    1 => [
                        [
                            'subject_code' => '3001',
                            'subject_name' => '实收资本',
                            'subject_type' => 3,
                            'loan_type' => 2
                        ]
                    ],
                    2 => [
                        [
                            'subject_code' => '4001',
                            'subject_name' => '实收资本',
                            'subject_type' => 4,
                            'loan_type' => 2
                        ]
                    ]

                ]
            ],
        ],
        [
            'no' => 49,
            'name' => '资本公积',
            'special_add_sub' => true, //特殊相加减
            'add' => [
                'accounting_system' => [
                    1 => [
                        [
                            'subject_code' => '3002',
                            'subject_name' => '资本公积',
                            'subject_type' => 3,
                            'loan_type' => 2
                        ]
                    ],
                    2 => [
                        [
                            'subject_code' => '4002',
                            'subject_name' => '资本公积',
                            'subject_type' => 4,
                            'loan_type' => 2
                        ]
                    ]

                ]
            ],
        ],
        [
            'no' => 50,
            'name' => '盈余公积',
            'special_add_sub' => true, //特殊相加减
            'add' => [
                'accounting_system' => [
                    1 => [
                        [
                            'subject_code' => '3101',
                            'subject_name' => '盈余公积',
                            'subject_type' => 3,
                            'loan_type' => 2
                        ]
                    ],
                    2 => [
                        [
                            'subject_code' => '4101',
                            'subject_name' => '盈余公积',
                            'subject_type' => 4,
                            'loan_type' => 2
                        ]
                    ]

                ]
            ],
        ],
        [
            'no' => 51,
            'name' => '未分配利润',
            'special_add_sub' => true, //特殊相加减
            'add' => [
                'accounting_system' => [
                    1 => [
                        [
                            'subject_code' => '3104',
                            'subject_name' => '利润分配',
                            'subject_type' => 3,
                            'loan_type' => 2
                        ]
                    ],
                    2 => [
                        [
                            'subject_code' => '4104',
                            'subject_name' => '利润分配',
                            'subject_type' => 4,
                            'loan_type' => 2
                        ]
                    ]

                ]
            ],
        ],
        [
            'no' => 52,
            'name' => '所有者权益（或股东权益）合计',
            'sum_no_range' => [48, 49, 50, 51],
            'default_show' => true, //默认显示文案，无任何数据
        ],
        [
            'no' => 53,
            'name' => '负债和所有者权益（或股东权益）总计',
            'sum_no_range' => [47, 52],
            'default_show' => true, //默认显示文案，无任何数据
        ],
    ]
];
