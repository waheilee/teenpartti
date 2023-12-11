<?php

namespace app\business\controller;



use app\model\UserDB;
use socket\QuerySocket;
use think\response\Json;

class Ranke  extends Main
{

    /**
     *  金币排行
     * @return View |    Json
     */
    public function coin()
    {
        switch (input('Action')) {
            case 'list':
                $db = new UserDB();
                $data = $db->GetGoldRanklist();
                $socket = new QuerySocket();
                foreach ($data['list'] as $k => &$v) {
                    $userbanlance = $socket->DSQueryRoleBalance($v['AccountID']);
                    $v['CashAble'] = 0;
                    if (!empty($userbanlance)) {
                        $v['iGameWealth'] = $userbanlance['iGameWealth'];
                        $v['iFreezonMoney'] = $userbanlance['iFreezonMoney'];
                        $v['iNeedWaged'] = $userbanlance['iNeedWaged'];
                        $v['iCurWaged'] = $userbanlance['iCurWaged'];
                        $v['CashAble'] = bcsub($v['iGameWealth'], $v['iFreezonMoney'], 2);
                    } else {
                        $v['iGameWealth'] = 0;
                        $v['iFreezonMoney'] = 0;
                        $v['iNeedWaged'] = 0;
                        $v['iCurWaged'] = 0;
                    }
                    ConVerMoney($v['CashAble']);
                    ConVerMoney($v['TotalWeath']);
                    ConVerMoney($v['iGameWealth']);
                    ConVerMoney($v['iFreezonMoney']);
                    ConVerMoney($v['iNeedWaged']);
                    ConVerMoney($v['iCurWaged']);
                    unset($v);
                }
                return $this->apiJson($data);

        }

        return $this->fetch();
    }
}