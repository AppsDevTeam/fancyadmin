<?php

namespace ADT\FancyAdmin\Model\Security;

use ADT\FancyAdmin\Model\Entities\AclRole;
use Doctrine\ORM\EntityManagerInterface;
use Nette\Http\Request;
use Nette\Security\AuthenticationException;
use Nette\Security\Authorizator;
use Nette\Security\IAuthenticator;
use Nette\Security\IIdentity;
use Nette\Security\Permission;
use Nette\Security\Resource;
use Nette\Security\UserStorage;
use SensitiveParameter;

trait SecurityUserTrait
{
	abstract protected function getAuthorizator(): Authorizator;
	abstract public function getIdentity(): ?IIdentity;

	protected Resource $fullDataAclResource;
	protected Resource $backofficeAclResource;
	protected Resource $personalDataAclResource;

	public function isAllowed($resource = Authorizator::All, $privilege = Authorizator::All): bool
	{
		return array_any(
			$this->getIdentity()->getRoles(),
			fn(AclRole $role) => $role->getIsAdmin() || $this->getAuthorizator()->isAllowed($role->getRoleId(), $resource, $privilege)
		);
	}

	public function isAdmin(): bool
	{
		return array_any(
			$this->getIdentity()->getRoles(),
			fn(AclRole $role) => $role->getIsAdmin()
		);
	}

	/**
	 * @throws AuthenticationException
	 */
	public function login(
		string|IIdentity $username,
		#[SensitiveParameter]
		?string $password = null,
		?string $context = null,
		array $metadata = []
	): void
	{
		if (empty($context)) {
			throw new \Exception('Context is required.');
		}

		parent::login($username, $password, $context, $metadata);
	}

	public function setFullDataAclResource(Resource $aclResource): void
	{
		$this->fullDataAclResource = $aclResource;
	}

	public function isAllowedFullDataAclResource(): bool
	{
		return $this->isAllowed($this->fullDataAclResource);
	}

	public function setBackofficeAclResource(Resource $aclResource): void
	{
		$this->backofficeAclResource = $aclResource;
	}

	public function isAllowedBackoffice(): bool
	{
		return $this->isAllowed($this->backofficeAclResource);
	}

	public function setPersonalDataAclResource(Resource $aclResource): void
	{
		$this->personalDataAclResource = $aclResource;
	}

	// Dokud neproběhla migrace balíčku, resource v authorizátoru chybí a isAllowed() by na
	// něj vyhodil InvalidStateException - neznámý resource proto bereme jako povoleno.
	public function isAllowedPersonalData(): bool
	{
		$authorizator = $this->getAuthorizator();

		if ($authorizator instanceof Permission && !$authorizator->hasResource($this->personalDataAclResource->getResourceId())) {
			return true;
		}

		return $this->isAllowed($this->personalDataAclResource);
	}
}
