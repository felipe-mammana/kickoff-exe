<?php

declare(strict_types=1);

return [
    ['GET', '/api/v1', ['ApiV1Controller', 'index'], false],
    ['GET', '/api/v1/health', ['ApiV1Controller', 'health'], false],
    ['GET', '/api/v1/me', ['ApiV1Controller', 'me'], true],
    ['GET', '/api/v1/device-types', ['ApiV1Controller', 'deviceTypes'], true, 'machines.view'],
    ['GET', '/api/v1/companies', ['ApiV1Controller', 'companies'], true, 'companies.view'],
    ['POST', '/api/v1/companies', ['ApiV1Controller', 'createCompany'], true, 'companies.create'],
    ['GET', '/api/v1/companies/(?P<id>\d+)', ['ApiV1Controller', 'company'], true, 'companies.view'],
    ['PUT', '/api/v1/companies/(?P<id>\d+)', ['ApiV1Controller', 'updateCompany'], true, 'companies.edit'],
    ['PATCH', '/api/v1/companies/(?P<id>\d+)', ['ApiV1Controller', 'updateCompany'], true, 'companies.edit'],
    ['DELETE', '/api/v1/companies/(?P<id>\d+)', ['ApiV1Controller', 'deactivateCompany'], true, 'companies.delete'],
    ['GET', '/api/v1/companies/(?P<id>\d+)/machines', ['ApiV1Controller', 'companyMachines'], true, 'machines.view'],
    ['POST', '/api/v1/companies/(?P<id>\d+)/machines', ['ApiV1Controller', 'createMachine'], true, 'machines.create'],
    ['GET', '/api/v1/machines/(?P<id>\d+)', ['ApiV1Controller', 'machine'], true, 'machines.view'],
    ['PUT', '/api/v1/machines/(?P<id>\d+)', ['ApiV1Controller', 'updateMachine'], true, 'machines.edit'],
    ['PATCH', '/api/v1/machines/(?P<id>\d+)', ['ApiV1Controller', 'updateMachine'], true, 'machines.edit'],
    ['DELETE', '/api/v1/machines/(?P<id>\d+)', ['ApiV1Controller', 'deactivateMachine'], true, 'machines.delete'],
    ['GET', '/api/v1/machines/(?P<id>\d+)/photos', ['ApiV1Controller', 'machinePhotos'], true, 'machines.view'],
    ['POST', '/api/v1/machines/(?P<id>\d+)/photos', ['ApiV1Controller', 'uploadMachinePhotos'], true, 'machines.edit'],
    ['DELETE', '/api/v1/machine-photos/(?P<id>\d+)', ['ApiV1Controller', 'deleteMachinePhoto'], true, 'machines.delete'],
];
