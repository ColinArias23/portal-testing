<?php

namespace App\Enums;

enum ApproverSource: string
{
    case ImmediateSupervisor = 'immediate_supervisor';
    case DepartmentHead = 'department_head';
    case Role = 'role';              
    case FixedUser = 'fixed_user'; 
}
