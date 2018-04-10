<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Blockchain;
use Ramsey\Uuid\Uuid;

class Block extends Controller
{

    # index
    public function Index(Request $request)
    {
        # 获取最新的节点信息
        return view('block');
    }

    # 循环获取我的amout
    public function Mine(Request $request){
        
    }

    # 挖掘新区域块
    public function creatBlock(Request $request)
    {
        $BC = new Blockchain();
        $node_identifier = Uuid::uuid4(time())->toString();
        # 我们运行工作证明算法来获得下一个证明。
        $last_block = $BC->last_block();
        $last_proof = $last_block['proof'];
        $proof = $BC->proof_of_work($last_proof);
        # 给工作量证明的节点提供奖励.
        # 发送者为 "0" 表明是新挖出的币
        $BC->new_transaction("0",$node_identifier,1);
        # 通过将其添加到链中来锻造新块
        $block = $BC->new_block("0",$proof);
        $response = array(
            'message' => "新区块",
            'index' => $block['index'],
            'transactions' => $block['transactions'],
            'proof' => $block['proof'],
            'previous_hash' => $block['previous_hash']
        );
        $str = array(
            'message' => "新区块".$block['index'].":   ".$block['previous_hash']
        );
        # 添加节点记录
        $BC->register_node(explode('/',$request->url())[2]);
        echo json_encode($str);
    }

    # 新的交易记录
    public function TransactionsNew(Request $request)
    {
        $BC = new Blockchain();
        $sender = $request->get('sender');
        $recipient = $request->get('recipient');
        $amount = $request->get('amount');
        if($sender == null || $recipient == null || $amount == null){
            return false;
        }       
        # 创建一个新的交易记录
        $index = $BC->new_transaction($sender,$recipient,$amount);
        $response = array(
            'message' => "交易将被添加到块 " . $index
        );
        print_r($response);
    }

    # 显示整个区块链
    public function Chain()
    {
        $BC = new Blockchain();
        $response = array(
            'chain' => $BC->getChain(),
            'length' => count($BC->getChain())
        );
        echo json_encode($response);
    }

    # 注册节点
    public function NodesRegister(Request $request)
    {
        $BC = new Blockchain();
        $nodes = $request->post('nodes');
        if($nodes == null){
            return "错误：请提供一个有效的节点列表";
        }
        foreach($nodes as $node){
            $BC->register_node($node);
        }
        $response = array(
            'message' => '新的节点已被添加',
            'total_nodes' => $BC->getNode(),
        );
        print_r($response);
    }

    # 解决节点冲突
    public function NodesResolve()
    {
        $BC = new Blockchain();
        $replaced = $BC->resolve_conflicts();
        if($replaced){
            $response = array(
                'message' => '我们的链已被取代',
                'new_chain' => $BC->getChain()
            );
        }else{
            $response = array(
                'message' => '我们的链是权威的',
                'new_chain' => $BC->getChain()
            );
        }
        echo json_encode($response);
        // print_r($response);
    }
}
