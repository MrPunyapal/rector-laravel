<?php

namespace RectorLaravel\Rector\Coalesce;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\BinaryOp\Coalesce;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Type\ObjectType;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\Rector\AbstractRector;
use RectorLaravel\ValueObject\ApplyDefaultInsteadOfNullCoalesce;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Webmozart\Assert\Assert;

/**
 * @see \RectorLaravel\Tests\Rector\Coalesce\ApplyDefaultInsteadOfNullCoalesceRector\ApplyDefaultInsteadOfNullCoalesceRectorTest
 */
final class ApplyDefaultInsteadOfNullCoalesceRector extends AbstractRector implements ConfigurableRectorInterface
{
    /**
     * @var ApplyDefaultInsteadOfNullCoalesce[]
     */
    private array $applyDefaultWith;

    public function __construct()
    {
        $this->applyDefaultWith = self::defaultLaravelMethods();
    }

    /**
     * @return ApplyDefaultInsteadOfNullCoalesce[]
     */
    public static function defaultLaravelMethods(): array
    {
        return [
            new ApplyDefaultInsteadOfNullCoalesce('config'),
            new ApplyDefaultInsteadOfNullCoalesce('env'),
            new ApplyDefaultInsteadOfNullCoalesce('data_get', argumentPosition: 2),
            new ApplyDefaultInsteadOfNullCoalesce('input', new ObjectType('Illuminate\Http\Request')),
            new ApplyDefaultInsteadOfNullCoalesce('get', new ObjectType('Illuminate\Support\Env')),
        ];
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Apply default instead of null coalesce',
            [
                new ConfiguredCodeSample(
                    <<<'CODE_SAMPLE'
custom_helper('app.name') ?? 'Laravel';
CODE_SAMPLE,
                    <<<'CODE_SAMPLE'
custom_helper('app.name', 'Laravel');
CODE_SAMPLE
                    ,
                    [
                        ...self::defaultLaravelMethods(),
                        new ApplyDefaultInsteadOfNullCoalesce('custom_helper'),
                    ],
                ),
            ]
        );
    }

    public function getNodeTypes(): array
    {
        return [Coalesce::class];
    }

    /**
     * @param  Coalesce  $node
     */
    public function refactor(Node $node): MethodCall|StaticCall|FuncCall|null
    {
        if (! $node->left instanceof FuncCall &&
            ! $node->left instanceof MethodCall &&
            ! $node->left instanceof StaticCall
        ) {
            return null;
        }

        if ($node->left->isFirstClassCallable()) {
            return null;
        }

        $call = $node->left;

        foreach ($this->applyDefaultWith as $applyDefaultWith) {
            $valid = false;

            $objectType = $call->var ?? $call->class ?? null;

            if (
                $applyDefaultWith->getObjectType() instanceof ObjectType &&
                $objectType !== null &&
                $this->isObjectType(
                    $objectType,
                    $applyDefaultWith->getObjectType()) &&
                $this->isName($call->name, $applyDefaultWith->getMethodName())
            ) {
                $valid = true;
            } elseif (
                $applyDefaultWith->getObjectType() === null &&
                $this->isName($call->name, $applyDefaultWith->getMethodName())
            ) {
                $valid = true;
            }

            if (! $valid) {
                continue;
            }

            if (count($call->args) === $applyDefaultWith->getArgumentPosition()) {
                if ($this->getType($node->right)->isNull()->yes()) {
                    return $call;
                }
                $call->args[count($call->args)] = new Arg($node->right);

                return $call;
            }
        }

        return null;
    }

    public function configure(array $configuration): void
    {
        Assert::allIsInstanceOf($configuration, ApplyDefaultInsteadOfNullCoalesce::class);
        $this->applyDefaultWith = $configuration;
    }
}
