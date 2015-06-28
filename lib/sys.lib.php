<?php
/**
 * 系统操作
 */

/**
 * 异步并行操作
 *
 *	@param $cmds = array(cmd1, cmd2, ..., cmdn)
 *	@param $timeout = 30(s)
 *	@param $max = count($cmds)
 *	
 */
function async_execution ($cmds, $timeout = 30, $max = 0) {
	// 检查参数
	if(empty($cmds)) return $cmds;
	// 默认开启和cmds同数量的进程
	if(!$max) $max = count($cmds);

	$commands = $cmds;
    // 保存句柄
	$handles = array();
    // 保存结果
	$results = array();
    // 开始时间
	$stime = time();

	for($i = 0; $i < $max && !empty($commands); $i++) {
		// 生成执行句柄
        $handle = proc_open(
        	array_shift($commands),
        	array( 0 => array("pipe", "r"), 1 => array("pipe", "w"), 2 => array("pipe", "w")), 
        	$pipes
        );
        // 设置非阻塞实现
        stream_set_blocking($pipes[1], 0);  
        $handles[] = array('handle' => $handle, 'pipes' => $pipes);
        $results[] = '';
    }

    while(true) {
        // 执行句柄
        foreach($handles as $idx => $handle) {
            // 如果句柄初始化失败或执行完成
            if(!is_resource($handle['handle']) || feof($handle['pipes'][1])) {
                // 关闭并移除句柄
                proc_close($handle['handle']); unset($handles[$idx]);
                // 如果命令列表还有数据
                if(!empty($commands)) {
                    $_handle = proc_open(
                        array_shift($commands), 
                        array(0 => array("pipe", "r"), 1 => array("pipe", "w"), 2 => array("pipe", "w")), 
                        $pipes
                    );
                    stream_set_blocking($pipes[1], 0);
                    $handles[] = array('handle' => $_handle, 'pipes' => $pipes);
                    $results[] = '';
                }
            }
            else { 
                $results[$idx] .= fgets($handle['pipes'][1], 1024);
            }
        }
        
        // 任务为空退出
        if(empty($handles)) break;
        // 超时退出
        if($timeout && (time() - $stime > $timeout) ) break;
    }

    return $results;
}

/*
$commands = array('echo a', 'echo b', 'echo c','echo d', 'echo e', 'echo a','echo a', 'echo a', 'echo a');
$results = async_execution($commands);
var_dump($results);
*/
