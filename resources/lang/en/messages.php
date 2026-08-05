<?php

return [
    'navigation' => [
        'recommendations' => 'Recommendations',
        'config_group' => 'Recommendations',
    ],

    'widgets' => [
        'recommendations' => [
            'heading' => 'Recommendations',
            'empty' => 'No recommendations available yet.',
            'view_product' => 'View product',
        ],
    ],

    'pages' => [
        'recommendation-settings' => [
            'navigation_label' => 'Recommendation Settings',
            'title' => 'Recommendation Settings',
            'tenant' => 'Tenant: :tenant',
            'default_tenant' => 'Default',
            'models_section' => 'Model Configuration',
            'no_models' => 'No recommendable models configured. Add them to recommendations.models in config/recommendations.php.',
            'strategy' => 'Strategy',
            'strategy_default' => 'Default (:strategy)',
            'strategy_helper' => 'The active strategy used to compute recommendations for this model.',
            'limit' => 'Result limit',
            'limit_helper' => 'Maximum number of recommendations returned.',
            'save' => 'Save settings',
            'saved' => 'Settings saved successfully.',
            'rebuild_section' => 'Last rebuild',
            'never' => 'Never',
            'rebuild_helper' => 'Last time recommendations were recomputed for this tenant.',
        ],
        'super-admin' => [
            'navigation_label' => 'Recommendations',
            'title' => 'Recommendations — Super Admin',
            'no_tenants' => 'No TenantProvider configured. Configure recommendations.tenancy.tenant_provider to list tenants.',
            'health_section' => 'Job health',
            'pending_jobs' => 'Pending jobs',
            'failed_jobs' => 'Failed jobs',
            'tenants_section' => 'Tenants',
            'tenant' => 'Tenant',
            'last_rebuild' => 'Last rebuild',
            'recalculate' => 'Recalculate',
            'recalculate_all' => 'Recalculate all',
            'recalculate_queued' => 'Recalculation queued',
            'documents' => 'Documentation',
        ],
    ],
];
