<?php

declare(strict_types=1);

namespace Rector\Transform\Rector\New_;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Type\ObjectType;
use Rector\Core\Contract\Rector\ConfigurableRectorInterface;
use Rector\Core\Rector\AbstractRector;
use Rector\Naming\Naming\PropertyNaming;
use Rector\Naming\ValueObject\ExpectedName;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\Transform\NodeFactory\PropertyFetchFactory;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * @see \Rector\Transform\Tests\Rector\New_\NewToConstructorInjectionRector\NewToConstructorInjectionRectorTest
 */
final class NewToConstructorInjectionRector extends AbstractRector implements ConfigurableRectorInterface
{
    /**
     * @var string
     */
    public const TYPES_TO_CONSTRUCTOR_INJECTION = 'TYPES_TO_CONSTRUCTOR_INJECTION';

    /**
     * @var ObjectType[]
     */
    private $constructorInjectionObjectTypes = [];

    /**
     * @var PropertyFetchFactory
     */
    private $propertyFetchFactory;

    /**
     * @var PropertyNaming
     */
    private $propertyNaming;

    public function __construct(PropertyFetchFactory $propertyFetchFactory, PropertyNaming $propertyNaming)
    {
        $this->propertyFetchFactory = $propertyFetchFactory;
        $this->propertyNaming = $propertyNaming;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('Change defined new type to constructor injection', [
            new ConfiguredCodeSample(<<<'CODE_SAMPLE'
class SomeClass
{
    public function run()
    {
        $validator = new Validator();
        $validator->validate(1000);
    }
}
CODE_SAMPLE
, <<<'CODE_SAMPLE'
class SomeClass
{
    /**
     * @var Validator
     */
    private $validator;

    public function __construct(Validator $validator)
    {
        $this->validator = $validator;
    }

    public function run()
    {
        $this->validator->validate(1000);
    }
}
CODE_SAMPLE
, [
                self::TYPES_TO_CONSTRUCTOR_INJECTION => ['Validator'],
            ]),
        ]);
    }

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [New_::class, Assign::class, MethodCall::class];
    }

    /**
     * @param New_|Assign|MethodCall $node
     */
    public function refactor(Node $node): ?Node
    {
        if ($node instanceof MethodCall) {
            return $this->refactorMethodCall($node);
        }
        if ($node instanceof Assign) {
            $this->refactorAssign($node);
        }
        if ($node instanceof New_) {
            $this->refactorNew($node);
        }
        return null;
    }

    /**
     * @param array<string, mixed[]> $configuration
     */
    public function configure(array $configuration): void
    {
        $typesToConstructorInjections = $configuration[self::TYPES_TO_CONSTRUCTOR_INJECTION] ?? [];
        foreach ($typesToConstructorInjections as $typesToConstructorInjection) {
            $this->constructorInjectionObjectTypes[] = new ObjectType($typesToConstructorInjection);
        }
    }

    private function refactorMethodCall(MethodCall $methodCall): ?MethodCall
    {
        foreach ($this->constructorInjectionObjectTypes as $constructorInjectionObjectType) {
            if (! $methodCall->var instanceof Variable) {
                continue;
            }

            if (! $this->isObjectType($methodCall->var, $constructorInjectionObjectType)) {
                continue;
            }

            $methodCall->var = $this->propertyFetchFactory->createFromType($constructorInjectionObjectType->getClassName());
            return $methodCall;
        }
        return null;
    }

    private function refactorAssign(Assign $assign): void
    {
        if (! $assign->expr instanceof New_) {
            return;
        }
        foreach ($this->constructorInjectionObjectTypes as $typesToConstructorInjection) {
            if (! $this->isObjectType($assign->expr, $typesToConstructorInjection)) {
                continue;
            }

            $this->removeNode($assign);
        }
    }

    private function refactorNew(New_ $new): void
    {
        foreach ($this->constructorInjectionObjectTypes as $constructorInjectionObjectType) {
            if (! $this->isObjectType($new->class, $constructorInjectionObjectType)) {
                continue;
            }

            $classLike = $new->getAttribute(AttributeKey::CLASS_NODE);
            if (! $classLike instanceof Class_) {
                continue;
            }

            $expectedPropertyName = $this->propertyNaming->getExpectedNameFromType($constructorInjectionObjectType);
            if (! $expectedPropertyName instanceof ExpectedName) {
                continue;
            }

            $this->addConstructorDependencyToClass($classLike, $constructorInjectionObjectType, $expectedPropertyName->getName());
        }
    }
}
