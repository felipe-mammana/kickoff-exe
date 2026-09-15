<?php

declare(strict_types=1);

return [
    ['GET', '/api/v1', ['ApiV1Controller', 'index'], false],
    ['GET', '/api/v1/health', ['ApiV1Controller', 'health'], false],
    ['GET', '/api/v1/me', ['ApiV1Controller', 'me'], true],
    ['GET', '/api/v1/device-types', ['ApiV1Controller', 'deviceTypes'], true],
    ['GET', '/api/v1/companies', ['ApiV1Controller', 'companies'], true],
    ['POST', '/api/v1/companies', ['ApiV1Controller', 'createCompany'], true, 'editor'],
    ['GET', '/api/v1/companies/(?P<id>\d+)', ['ApiV1Controller', 'company'], true],
    ['PUT', '/api/v1/companies/(?P<id>\d+)', ['ApiV1Controller', 'updateCompany'], true, 'editor'],
    ['PATCH', '/api/v1/companies/(?P<id>\d+)', ['ApiV1Controller', 'updateCompany'], true, 'editor'],
    ['DELETE', '/api/v1/companies/(?P<id>\d+)', ['ApiV1Controller', 'deactivateCompany'], true, 'admin'],
    ['GET', '/api/v1/companies/(?P<id>\d+)/machines', ['ApiV1Controller', 'companyMachines'], true],
    ['POST', '/api/v1/companies/(?P<id>\d+)/machines', ['ApiV1Controller', 'createMachine'], true, 'editor'],
    ['GET', '/api/v1/machines/(?P<id>\d+)', ['ApiV1Controller', 'machine'], true],
    ['PUT', '/api/v1/machines/(?P<id>\d+)', ['ApiV1Controller', 'updateMachine'], true, 'editor'],
    ['PATCH', '/api/v1/machines/(?P<id>\d+)', ['ApiV1Controller', 'updateMachine'], true, 'editor'],
    ['DELETE', '/api/v1/machines/(?P<id>\d+)', ['ApiV1Controller', 'deactivateMachine'], true, 'admin'],
    ['GET', '/api/v1/machines/(?P<id>\d+)/photos', ['ApiV1Controller', 'machinePhotos'], true],
    ['POST', '/api/v1/machines/(?P<id>\d+)/photos', ['ApiV1Controller', 'uploadMachinePhotos'], true, 'editor'],
    ['DELETE', '/api/v1/machine-photos/(?P<id>\d+)', ['ApiV1Controller', 'deleteMachinePhoto'], true, 'admin'],
];
