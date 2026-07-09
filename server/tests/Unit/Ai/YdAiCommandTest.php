<?php
// server/tests/Unit/Ai/YdAiCommandTest.php
declare(strict_types=1);

namespace tests\Unit\Ai;

use app\command\YdAiCommand;
use core\ai\AiClientException;
use core\ai\SchemaException;
use ReflectionMethod;
use think\console\Input;
use tests\TestCase;

class YdAiCommandTest extends TestCase
{
    private function buildInput(array $tokens, bool $interactive = true): Input
    {
        $command = new YdAiCommand();
        $input = new Input($tokens);
        $input->bind($command->getDefinition());
        $input->setInteractive($interactive);
        return $input;
    }

    private function invokeShouldAskOptin(YdAiCommand $command, Input $input): bool
    {
        $method = new ReflectionMethod(YdAiCommand::class, 'shouldAskOptin');
        $method->setAccessible(true);
        return $method->invoke($command, $input);
    }

    /**
     * I1 回归测试：--write 选项跳过 opt-in 询问，避免 CI 中 $output->ask() 因 stdin EOF 抛异常崩溃
     */
    public function testShouldAskOptinFalseWhenWriteOptionSet(): void
    {
        $command = new YdAiCommand();
        $input = $this->buildInput(['测试', '--write'], true);

        $this->assertFalse($this->invokeShouldAskOptin($command, $input));
    }

    /**
     * I1 回归测试：非交互环境（如 CI，stdin 不可用）跳过 opt-in 询问
     */
    public function testShouldAskOptinFalseWhenNonInteractive(): void
    {
        $command = new YdAiCommand();
        $input = $this->buildInput(['测试'], false);

        $this->assertFalse($this->invokeShouldAskOptin($command, $input));
    }

    /**
     * I1 回归测试：正常交互环境且未指定 --write 时才询问 opt-in
     */
    public function testShouldAskOptinTrueWhenInteractiveAndNoWriteOption(): void
    {
        $command = new YdAiCommand();
        $input = $this->buildInput(['测试'], true);

        $this->assertTrue($this->invokeShouldAskOptin($command, $input));
    }

    /**
     * I4 回归测试：SchemaException（数据表读取失败）映射为退出码 1
     */
    public function testExitCodeForSchemaExceptionIsOne(): void
    {
        $command = new YdAiCommand();
        $method = new ReflectionMethod(YdAiCommand::class, 'exitCodeFor');
        $method->setAccessible(true);

        $this->assertSame(1, $method->invoke($command, new SchemaException("数据表 'x' 不存在")));
    }

    /**
     * I4 回归测试：其余 AiClientException（引擎连接/响应异常）映射为退出码 2，
     * 不再依赖对错误文案做 str_contains('数据表') 判断
     */
    public function testExitCodeForGenericAiClientExceptionIsTwo(): void
    {
        $command = new YdAiCommand();
        $method = new ReflectionMethod(YdAiCommand::class, 'exitCodeFor');
        $method->setAccessible(true);

        $this->assertSame(2, $method->invoke($command, new AiClientException('无法连接 AI 引擎')));
        // 即便远端错误文案恰好包含“数据表”字样，只要不是 SchemaException 仍应为 2
        $this->assertSame(2, $method->invoke($command, new AiClientException('引擎错误：数据表相关的下游报错')));
    }
}
