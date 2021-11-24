<?php

use Ramona\AutomationPlatformLibBuild\Actions\BuildDockerImage;
use Ramona\AutomationPlatformLibBuild\Actions\Group;
use Ramona\AutomationPlatformLibBuild\Actions\KustomizeApply;
use Ramona\AutomationPlatformLibBuild\Actions\PutFile;
use Ramona\AutomationPlatformLibBuild\Actions\PutRuntimeConfiguration;
use Ramona\AutomationPlatformLibBuild\BuildDefinition;
use Ramona\AutomationPlatformLibBuild\Context;
use Ramona\AutomationPlatformLibBuild\Definition\BuildDefinitionBuilder;
use Ramona\AutomationPlatformLibBuild\Rust\TargetGenerator;
use Ramona\AutomationPlatformLibBuild\Target;
use Ramona\AutomationPlatformLibBuild\TargetId;

return static function (BuildDefinitionBuilder $builder) {
    $rustTargetGenerator = new TargetGenerator(__DIR__);

    $override = static function (Context $context):string {
        return <<<EOT
        apiVersion: apps/v1
        kind: Deployment
        metadata:
          name: svc-directory-watcher
        spec:
          template:
            metadata:
              labels:
                app: svc-directory-watcher
            spec:
              initContainers:
                - name: migrations
                  image: {$context->artifactCollector()->getByKey(__DIR__, 'image-migrations')->name()}
              containers:
                - name: app
                  image: {$context->artifactCollector()->getByKey(__DIR__, 'image-service')->name()}
        EOT;
    };

    foreach ($rustTargetGenerator->targets() as $target) {
        $builder->addTarget($target);
    }

    $builder->addTarget(
        new Target(
            'build',
            new PutFile(__DIR__.'/k8s/overlays/dev/deployment.yaml', $override),
            array_merge(
                $rustTargetGenerator->buildTargetIds(),
                [
                    new TargetId(__DIR__, 'build-images')
                ]
            )
        )
    );

    $builder->addTarget(
        new Target(
            'build-images',
            new Group([
                new PutRuntimeConfiguration(__DIR__.'/runtime.configuration.json'),
                new BuildDockerImage('image-service', 'automation-platform-svc-directory-watcher', '../../', 'docker/Dockerfile'),
                new BuildDockerImage('image-migrations', 'automation-platform-svc-migrations', '.', 'docker/migrations.Dockerfile'),
            ])
        )
    );

    $builder->addTarget(
        new Target(
            'deploy',
            new KustomizeApply('k8s/overlays/dev'),
            [new TargetId(__DIR__, 'build'), new TargetId(__DIR__.'/../events/', 'deploy')]
        )
    );
};
