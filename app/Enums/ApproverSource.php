<?php

namespace App\Enums;

enum ApproverSource: string
{
    case ImmediateSupervisor = 'immediate_supervisor';
    case DepartmentHead = 'department_head';
    case ManagementChain = 'management_chain';
    case Role = 'role';
    case FixedUser = 'fixed_user';
}