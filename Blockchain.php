<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class Blockchain{      

    # 区块链
    public  $chain;
    # 交易记录
    public  $current_transactions;
    # 节点
    public  $node;

    public function __construct()
    {
    	/*$point = Redis::hvals('0404point');
    	echo array_sum($point); 	
    	dd($point);*/

        $this->chain = json_decode( Redis::get('BlockChain'),true);        
        $this->node = json_decode(Redis::get('BlockNode'),true);
        $this->current_transactions = json_decode(Redis::get('thisTransaction'),true);

        # 创建创世块
        if( count($this->last_block()) == 0 ){
            $this->new_block('1',100);
        }         
    }    
    
    # 获取区块链信息
    public function getChain()
    {
        return $this->chain;
    }

    # 获取交易信息
    public function getTran(Type $var = null)
    {
        return $this->current_transactions;
    }

    # 获取节点信息
    public function getNode(Type $var = null)
    {
        return $this->node;
    }

    /*
        将新节点添加到节点列表中
        :param address: <str> 节点的地址
        :return: None
    */
    public function register_node($address)
    {
        $this->node[] = $address;
        Redis::set('BlockNode',json_encode( $this->node ));
    }

    /*
        确定给定的区块链是否有效
        :param chain: <list> 区块链
        :return: <bool> 如果有效则为真，否则为假
    */
    public function valid_chain($chain)
    {
        $last_block = $chain[0];
        $current_index = 1;
        while($current_index < count($chain)){
            $block = $chain[$current_index];
            # 检查块的散列是否正确
            if($block['previous_hash'] != $this->hash($last_block)){
                return false;
            }
            # 检查工作证明是否正确
            if( ! $this->valid_proof($last_block['proof'],$block['proof'])){
                return false;
            }
            $last_block = $block;
            $current_index ++;
        }
        return true;
    }

    /*
        共识算法解决冲突
        使用网络中最长的链.
        :return: <bool> True 如果链被取代, 否则为False
    */
    public function resolve_conflicts()
    {
        $neighbours = $this->node;
        # 去除重复的节点
        $neighbours = array_unique($neighbours);
        $new_chain = null;
        # 我们只是在寻找比我们更长的链条
        $max_length = count($this->chain);
        # 抓取并验证我们网络中所有节点的链
        foreach($neighbours as $node){
            $url = "http://".$node."/block/chain";
            $ch  = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);  //返回数据不直接输出
            $content = curl_exec($ch);                    //执行并存储结果
            curl_close($ch);
            if($content == null)
                continue;
            $content = json_decode($content,true);
            $length = $content['length'];
            $chain = $content['chain'];
            # 检查长度是否更长，链条是否有效
            if($length > $max_length && $this->valid_chain($chain)){
                $max_length = $length;
                $new_chain = $chain;
            }
        }
        # 如果我们发现一个比我们的更长的新的有效链条，就取代我们的链条
        if($new_chain != null){
            $this->chain = $new_chain;
            Redis::set('BlockChain',json_encode( $this->chain ));
            return true;
        }
        return false;
    }

    /*
        生成新块        
        :param previous_hash: (Optional) <str> 前面的块的哈希
        :param proof: <int> 证明工作算法给出的证明
        :return: <dict> 新的块
    */
    public function new_block($previous_hash,$proof)
    {
        $previous_hash = $previous_hash != "1" ? $this->hash($this->last_block()) : $previous_hash;

        # 创建一个新的块并将其添加到链中
        $tmpBlock = array(
            'index' => count(@$this->chain) + 1,
            'timestamp' => time(),
            'transactions' => @$this->current_transactions,
            'proof' => $proof,
            'previous_hash' => $previous_hash
        );
        # 清空redis里的当前交易记录
        Redis::set('thisTransaction',json_encode([]));;
        $this->chain[] = $tmpBlock;
        Redis::set('BlockChain',json_encode( $this->chain ));
        return $tmpBlock;
    }

    /*
        生成新交易信息，信息将加入到下一个待挖的区块中
        :param sender: <str> 发件人的地址
        :param recipient: <str> 收件人的地址
        :param amount: <int> 数量
        :return: <int> 将持有此交易的Block的索引
    */    
    public function new_transaction($sender,$recipient,$amount)
    {
        # 将新的交易添加到交易列表        
        $this->current_transactions[] = array(
            'sender' => $sender,
            'recipient' => $recipient,
            'amount' => $amount
        );
        return $this->last_block();
    }

    /*
        简单的工作量证明:
         - 查找一个 p' 使得 hash(pp') 以4个0开头
         - p 是上一个块的证明,  p' 是当前的证明
        :param last_proof: <int>
        :return: <int>
    */
    public function proof_of_work($last_proof)
    {
        $proof = 0;
        while(!$this->valid_proof($last_proof,$proof)){
            $proof ++;
        }
        return $proof;
    }

    /*
        验证证明: 是否hash(last_proof, proof)以4个0开头?
        :param last_proof: <int> 先前的证明
        :param proof: <int> 当前证明
        :return: <bool> 如果正确则为真，否则为假。
    */
    public function valid_proof($last_proof,$proof)
    {
        $guess = $last_proof . $proof;
        $guess_hash = bin2hex(hash('sha256', $guess, true));
        return preg_match('/^0000/is', $guess_hash ) ? true : false;
    }
    
    /*
        生成块的 SHA-256 hash值
        :param block: <dict> 区块
        :return: <str>
    */
    public function hash($block)
    {
        # Hash区块
        # 我们必须确保字典是有序的，否则我们将有不一致的哈希值
        $block_string = json_encode($block);
        return bin2hex(hash('sha256', $block_string, true));
    }

    # 返回链中的最后一个块
    public function last_block()
    {
        return @end($this->chain);
    }
}

