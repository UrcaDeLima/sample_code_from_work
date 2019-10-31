<?
/**
* 
*/
class Helper
{
	
	function __construct()
	{
		
	}

	/* Универсальная функция для получения принадлежности пользователя к группе */
    public static function IsUserGroup($groupCode, $userID = false)
    {
        $userID = !empty($userID) ? $userID : $GLOBALS['USER']->GetID();

        $arFilter = array("ACTIVE" => "Y");
        $res = CGroup::GetList(($by), ($order), $arFilter);
        while($row = $res->Fetch()) {
            $arGroups[$row['ID']] = $row['STRING_ID'];
        }

        $arUserGroups = CUser::GetUserGroup($userID);

        foreach($arUserGroups as $groupID) {
            if($arGroups[$groupID] == $groupCode) {
                return true;
            }
        }

        return false;
    }

    // клиент ли это?
	public static function IsClient($userID = false) 
	{
		if(empty($userID)) {
			global $USER;
			$userID = $USER->GetID();
		}

		$arUserGroups = CUser::GetUserGroup($userID);

		$arClientGroups = [1, 2, 3, 4, 40];

		$isClient = true;
		foreach($arUserGroups as $groupID) {
			if(!in_array($groupID, $arClientGroups)) {
				$isClient = false;
				break;
			}
		}

		return $isClient;
	}

	// исследуем роль и разбиваем её на тип заявителя и должность
	public static function ExplodeRole($userID)
	{
		if(empty($userID)) {
			global $USER;
			$userID = $USER->GetID();
		}

		$res = \CGroup::GetList(($by), ($order));
		while($row = $res->Fetch()) {
			$arGroups[$row['ID']] = $row;
		}

		$arUserGroups = \CUser::GetUserGroup($userID);

		$arClientGroups = [1, 2, 3, 4];

		foreach ($arUserGroups as $k_group => $groupID) {
			if(!in_array($groupID, $arClientGroups)) {
				$code = $arGroups[$groupID]['STRING_ID'];
				$explode = explode("-", $code, 2);
				break;
			}
		}

		return $explode;
	}

	/**
	 * Методы проверки на менеджера, куратора, босса, исходя из кода роли (т.е. dd-manager - это менеджер, потому что после дефиса manager) 
	 */
	public static function IsManager($userID)
	{
		$explode = self::ExplodeRole($userID);
		return (strtolower($explode[1]) == 'manager');
	}
	public static function IsCurator($userID)
	{
		$explode = self::ExplodeRole($userID);
		return (strtolower($explode[1]) == 'curator');
	}
	public static function IsBoss($userID)
	{
		$explode = self::ExplodeRole($userID);
		return (strtolower($explode[1]) == 'boss');
	}
	public static function IsMainManager($userID)
	{
		$explode = self::ExplodeRole($userID);
		return (strtolower($explode[1]) == 'main-manager');
	}

	public static function TypeApp($userID)
	{
		$explode = self::ExplodeRole($userID);
		return strtolower($explode[0]);
	}

	public static function GetRoleParams($roleName)
	{
		global $SETTINGS;
		$arMainRoles = ['MANAGER', 'CURATOR', 'BOSS', 'MAIN_MANAGER'];

		if(empty($roleName)) {
			$roleName = \Helper\User::GetRole();
		}

		$arFilter =[];

		foreach($arMainRoles as $k_role => $role) {
			if(array_key_exists($roleName, $SETTINGS[$role]['ROLE'])) {
				$arFilter['TITLE'] = $SETTINGS[$role]['TITLE'];
				$arFilter['TEMPLATE'] = $SETTINGS[$role]['TEMPLATE'];
				$arFilter['FILTER'] = $SETTINGS[$role]['PARAMS'];
				foreach ($SETTINGS[$role]['ROLE'][$roleName]['FILTER'] as $key => $value) {
					$arFilter['FILTER'][$key] = $value;
				}
				break;
			}
		}

		return $arFilter;
	}

	public static function GetStatuses()
    {
    	global $INFO;
    	if(empty($INFO['STATUSES'])) {
	        \Bitrix\Main\Loader::includeModule('iblock');
	        $IBLOCK_ID = \CIBlock::GetList([], ['CODE' => IBLOCK_CODE_APPLICATION_STATUSES])->Fetch()['ID'];
	        $res = \CIBlockElement::GetList([], ['IBLOCK_ID' => $IBLOCK_ID, 'ACTIVE' => 'Y']);
	        while($row = $res->Fetch()) {
	        	$arStatus[$row['ID']] = $row;
	        }
	        foreach($arStatus as $key => $value) {
		        $res = \CIBlockElement::GetProperty($IBLOCK_ID, $value['ID'], [], ['ACTIVE' => 'Y', 'EMPTY' => 'N']);
		        while($row = $res->Fetch()) {
		            $arProperty[$row['CODE']] = $row;
		        }
		        $arStatus[$key]['PROPERTIES'] = $arProperty;
		    }
		    $INFO['STATUSES'] = $arStatus;
		}

        return $INFO['STATUSES'];
    }

    public static function GetKindDocuments()
    {
    	global $INFO;
    	if(empty($INFO['KIND_DOCUMENTS'])) {
    		\Bitrix\Main\Loader::includeModule('iblock');
	        $res = \CIBlockElement::GetList([], ['IBLOCK_CODE' => IBLOCK_CODE_KIND_DOCUMENT, 'ACTIVE' => 'Y']);
	        while($row = $res->GetNextElement()) {
	        	$fields = $row->GetFields();
	        	$props = $row->GetProperties();
	        	$arKindDocuments[$fields['ID']] = $fields;
	        	$arKindDocuments[$fields['ID']]['PROPERTIES'] = $props;
	        }
		    $INFO['KIND_DOCUMENTS'] = $arKindDocuments;
		}

        return $INFO['KIND_DOCUMENTS'];
    }

    public static function GetCompanyByID($companyID)
    {
    	try {
    		if(empty($companyID)) {
    			throw new Exception("Не указан ID контрагента", 1001);
    		}
    	} catch (Exception $e) {
    		return $e;
    	}
    	\Bitrix\Main\Loader::includeModule('iblock');
    	$res = \CIBlockElement::GetList([], ['IBLOCK_CODE' => IBLOCK_CODE_COMPANY, 'ACTIVE' => 'Y', 'ID' => $companyID]);
    	$row = $res->GetNextElement();
		$arCompany = $row->GetFields();
		$arCompany['PROPERTIES'] = $row->GetProperties();
		return $arCompany;
    }

    /**
     * Функция получения элементов через $res
     * @param CIBlockResult $res 
     */
    public static function GetElements($res)
    {
    	\Bitrix\Main\Loader::includeModule('iblock');
    	$arResult = [];
    	while($row = $res->GetNextElement()) {
    		$fields = $row->GetFields();
    		$props = $row->GetProperties();
    		$fields['PROPERTIES'] = $props;
    		$arResult[$fields['ID']] = $fields;
    	}
    	return $arResult;
    }

    public static function GetContragentStatuses()
    {
    	\Bitrix\Main\Loader::includeModule('iblock');

    	$res = \CIBlockElement::GetList([], ['IBLOCK_CODE' => 'company-status', 'ACTIVE' => 'Y']);

    	$arResult = \Helper::GetElements($res);

    	return $arResult;
    }

    public static function GetContragentTypes()
    {
    	\Bitrix\Main\Loader::includeModule('iblock');

    	$res = \CIBlockElement::GetList([], ['IBLOCK_CODE' => IBLOCK_CODE_COMPANY_TYPE, 'ACTIVE' => 'Y']);

    	$arResult = \Helper::GetElements($res);

    	return $arResult;
    }

    public static function GetIBlockIDByCode($code)
    {
    	\Bitrix\Main\Loader::includeModule('iblock');

    	return \CIBlock::GetList([], ['CODE' => $code, 'CHECK_PERMISSIONS' => 'N'])->Fetch()['ID'];
    }
}
