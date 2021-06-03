<?php

namespace Rapyd\ERD;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use ReflectionClass;

class ModelFinder
{
  protected $filesystem;

  public function __construct(Filesystem $filesystem)
  {
    $this->filesystem = $filesystem;
  }

  public function getModelsInDirectory(string $directory, $model_scope): Collection
  {
    $files = config('erd-generator.recursive') ?
      $this->filesystem->allFiles($directory) :
      $this->filesystem->files($directory);

    $ignoreModels = array_filter(config('erd-generator.ignore', []), 'is_string');

    if ($model_scope == 'false') { 
      $whitelistModels = array_filter(config('erd-generator.whitelist', []), 'is_string');
    } else {
      if ($model_scope == 'policies') {
        $whitelistModels = [
          "Rapyd\Model\BondLibraries",
          "Rapyd\Model\BondPolicies",
          "Rapyd\Model\BondLibraryObligee",
          "Rapyd\Model\PolicyAccountingInvoices",
          "Rapyd\Model\PolicyAccountingPayments",
          "Rapyd\Model\PolicyAddresses",
          "Rapyd\Model\PolicyAttachedAuthMethods",
          "Rapyd\Model\PolicyCancellations",
          "Rapyd\Model\PolicyCommissionSched",
          "Rapyd\Model\PolicyDishonesty",
          "Rapyd\Model\PolicyErisa",
          "Rapyd\Model\PolicyFees",
          "Rapyd\Model\PolicyFiles",
          "Rapyd\Model\PolicyGenericObligees",
          "Rapyd\Model\PolicyHistory",
          "Rapyd\Model\PolicyJoblocations",
          "Rapyd\Model\PolicyQuotes",
          "Rapyd\Model\PolicyStatus",
          "Rapyd\Model\PolicyVehicles",
          "Rapyd\Model\SuretyCompanies",
          "App\User",
          "Rapyd\Model\Usergroups",
        ];
      } elseif ($model_scope == 'bondlibrary') {
        $whitelistModels = [
          "Rapyd\Model\BondEdit",
          "Rapyd\Model\BondLibraries",
          "Rapyd\Model\BondLibraryClass",
          "Rapyd\Model\BondLibraryForm",
          "Rapyd\Model\BondLibraryFormSublimit",
          "Rapyd\Model\BondLibraryIndustry",
          "Rapyd\Model\BondLibraryLimit",
          "Rapyd\Model\BondLibraryLimit",
          "Rapyd\Model\BondLibraryObligee",
          "Rapyd\Model\BondLibraryObligeeType",
          "Rapyd\Model\BondLibraryPricing",
          "Rapyd\Model\BondLibraryPricingSubgroup",
          "Rapyd\Model\BondLibraryPricingSubgroupYearly",
          "Rapyd\Model\BondLibraryRenewal",
          "Rapyd\Model\BondLibraryState",
          "Rapyd\Model\BondLibraryType",
        ];
      } elseif ($model_scope == 'user') {
        $whitelistModels = [
          "App\User",
          "Rapyd\Model\BondPolicies",
          "Rapyd\Model\CmsBlogPost",
          "Rapyd\Model\CreditReport",
          "Rapyd\Model\CreditReportAddress",
          "Rapyd\Model\CreditReportInquiry",
          "Rapyd\Model\CreditReportName",
          "Rapyd\Model\CreditReportPublic",
          "Rapyd\Model\CreditReportRiskModel",
          "Rapyd\Model\CreditReportSummary",
          "Rapyd\Model\CreditReportTradeline",
          "Rapyd\Model\Usergroups",
          "Rapyd\Model\UsergroupType",
        ];
      } elseif ($model_scope == 'system') {
        $whitelistModels = [
          "Rapyd\Model\CreditReportSettings",
          "Rapyd\Model\Events",
          "Rapyd\Model\RapydConvos",
          "Rapyd\Model\RapydMessages",
          "Rapyd\Model\Redirectors",
          "Rapyd\Model\SchedulerResults",
          "Rapyd\Model\SchedulerTasks",
          "Rapyd\Model\Settings",
          "Rapyd\Model\SitemapSettings"
        ];
      } elseif ($model_scope == 'cms') {
        $whitelistModels = [
          "Rapyd\Model\CmsBlogPost",
          "Rapyd\Model\CmsCategory",
          "Rapyd\Model\CmsContentWrapper",
          "Rapyd\Model\CmsPage",
          "Rapyd\Model\Poll",
          "Rapyd\Model\PollOption",
          "Rapyd\Model\PollVote",
          "App\User"
        ];
      }
    }


    $collection = Collection::make($files)->filter(function ($path) {
        return Str::endsWith($path, '.php');
    })->map(function ($path) {
        return $this->getFullyQualifiedClassNameFromFile($path);
    })->filter(function (string $className) {
        return  !empty($className) &&
                is_subclass_of($className, EloquentModel::class) &&
                !(new ReflectionClass($className))->isAbstract();
    });

    if(!count($whitelistModels)) {
      return $collection->diff($ignoreModels)->sort();
    }

    return $collection->filter(function (string $className) use ($whitelistModels) {
      return in_array($className, $whitelistModels);
    });
  }

  protected function getFullyQualifiedClassNameFromFile(string $path): string
  {
    $parser     = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
    $traverser  = new NodeTraverser();
    $traverser->addVisitor(new NameResolver());

    $code       = file_get_contents($path);
    $statements = $parser->parse($code);
    $statements = $traverser->traverse($statements);

    // get the first namespace declaration in the file
    $root_statement = collect($statements)->first(function ($statement) {
      return $statement instanceof Namespace_;
    });

    if (! $root_statement) {
      return '';
    }

    return collect($root_statement->stmts)
      ->filter(function ($statement) {
          return $statement instanceof Class_;
      })
      ->map(function (Class_ $statement) {
          return $statement->namespacedName->toString();
      })
      ->first() ?? '';
  }
}
