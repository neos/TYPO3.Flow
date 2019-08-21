<?php
namespace Neos\Flow\Tests\Unit\Security\Authorization;

/*
 * This file is part of the Neos.Flow package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Flow\Aop\JoinPoint;
use Neos\Flow\Aop\JoinPointInterface;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Neos\Flow\Security\Authorization\Privilege\AbstractPrivilege;
use Neos\Flow\Security\Authorization\Privilege\Method\MethodPrivilegeInterface;
use Neos\Flow\Security\Authorization\Privilege\Method\MethodPrivilegeSubject;
use Neos\Flow\Security\Authorization\Privilege\PrivilegeInterface;
use Neos\Flow\Security\Authorization\PrivilegeManager;
use Neos\Flow\Security\Context;
use Neos\Flow\Security;
use Neos\Flow\Tests\UnitTestCase;

/**
 * Testcase for the privilege manager
 *
 */
class PrivilegeManagerTest extends UnitTestCase
{
    /**
     * @var Context|\PHPUnit\Framework\MockObject\MockObject
     */
    protected $mockSecurityContext;

    /**
     * @var ObjectManagerInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    protected $mockObjectManager;

    /**
     * @var JoinPointInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    protected $mockJoinPoint;

    /**
     * @var PrivilegeInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    protected $grantPrivilege;

    /**
     * @var PrivilegeInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    protected $denyPrivilege;

    /**
     * @var PrivilegeInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    protected $abstainPrivilege;

    /**
     * @var PrivilegeManager
     */
    protected $privilegeManager;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->mockSecurityContext = $this->getMockBuilder(Context::class)->disableOriginalConstructor()->getMock();
        $this->mockObjectManager = $this->getMockBuilder(ObjectManagerInterface::class)->getMock();
        $this->mockJoinPoint = $this->getMockBuilder(JoinPoint::class)->disableOriginalConstructor()->getMock();

        $this->privilegeManager = new PrivilegeManager($this->mockObjectManager, $this->mockSecurityContext);

        $this->grantPrivilege = $this->getMockBuilder(AbstractPrivilege::class)->disableOriginalConstructor()->getMock();
        $this->grantPrivilege->expects($this->any())->method('getPermission')->will(self::returnValue(PrivilegeInterface::GRANT));
        $this->grantPrivilege->expects($this->any())->method('matchesSubject')->will(self::returnValue(true));
        $this->grantPrivilege->expects($this->any())->method('getParameters')->will(self::returnValue([]));
        $this->grantPrivilege->expects($this->any())->method('isGranted')->will(self::returnValue(true));
        $this->grantPrivilege->expects($this->any())->method('isDenied')->will(self::returnValue(false));

        $this->denyPrivilege = $this->getMockBuilder(AbstractPrivilege::class)->disableOriginalConstructor()->getMock();
        $this->denyPrivilege->expects($this->any())->method('getPermission')->will(self::returnValue(PrivilegeInterface::DENY));
        $this->denyPrivilege->expects($this->any())->method('matchesSubject')->will(self::returnValue(true));
        $this->denyPrivilege->expects($this->any())->method('getParameters')->will(self::returnValue([]));
        $this->denyPrivilege->expects($this->any())->method('isGranted')->will(self::returnValue(false));
        $this->denyPrivilege->expects($this->any())->method('isDenied')->will(self::returnValue(true));

        $this->abstainPrivilege = $this->getMockBuilder(AbstractPrivilege::class)->disableOriginalConstructor()->getMock();
        $this->abstainPrivilege->expects($this->any())->method('getPermission')->will(self::returnValue(PrivilegeInterface::ABSTAIN));
        $this->abstainPrivilege->expects($this->any())->method('matchesSubject')->will(self::returnValue(true));
        $this->abstainPrivilege->expects($this->any())->method('getParameters')->will(self::returnValue([]));
        $this->abstainPrivilege->expects($this->any())->method('isGranted')->will(self::returnValue(false));
        $this->abstainPrivilege->expects($this->any())->method('isDenied')->will(self::returnValue(false));
    }

    /**
     * @test
     */
    public function isGrantedGrantsIfNoPrivilegeWasConfigured()
    {
        $role1ClassName = 'role1' . md5(uniqid(mt_rand(), true));
        $role2ClassName = 'role2' . md5(uniqid(mt_rand(), true));

        $mockRoleAdministrator = $this->createMock(Security\Policy\Role::class, [], [], $role1ClassName, false);
        $mockRoleAdministrator->expects($this->any())->method('getPrivilegesByType')->will(self::returnValue([]));

        $mockRoleCustomer = $this->createMock(Security\Policy\Role::class, [], [], $role2ClassName, false);
        $mockRoleCustomer->expects($this->any())->method('getPrivilegesByType')->will(self::returnValue([]));

        $this->mockSecurityContext->expects(self::once())->method('getRoles')->will(self::returnValue([$mockRoleAdministrator, $mockRoleCustomer]));

        self::assertTrue($this->privilegeManager->isGranted(MethodPrivilegeInterface::class, $this->mockJoinPoint));
    }

    /**
     * @test
     */
    public function isGrantedGrantsAccessIfNoRolesAreAvailable()
    {
        $this->mockSecurityContext->expects(self::once())->method('getRoles')->will(self::returnValue([]));

        self::assertTrue($this->privilegeManager->isGranted(MethodPrivilegeInterface::class, $this->mockJoinPoint));
    }

    /**
     * @test
     */
    public function isGrantedGrantsAccessIfNoPolicyEntryCouldBeFound()
    {
        $testRole1 = $this->getAccessibleMock(Security\Policy\Role::class, ['getPrivilegesByType'], ['Acme.Demo:TestRole1']);
        $testRole1->expects(self::once())->method('getPrivilegesByType')->with(MethodPrivilegeInterface::class)->will(self::returnValue([]));

        $this->mockSecurityContext->expects(self::once())->method('getRoles')->will(self::returnValue([$testRole1]));

        self::assertTrue($this->privilegeManager->isGranted(MethodPrivilegeInterface::class, $this->mockJoinPoint));
    }

    /**
     * @test
     */
    public function isGrantedDeniesAccessIfADenyPrivilegeWasConfiguredForOneOfTheRoles()
    {
        $role1ClassName = 'role1' . md5(uniqid(mt_rand(), true));
        $role2ClassName = 'role2' . md5(uniqid(mt_rand(), true));

        $mockRoleAdministrator = $this->createMock(Security\Policy\Role::class, [], [], $role1ClassName, false);
        $mockRoleAdministrator->expects($this->any())->method('getPrivilegesByType')->will(self::returnValue([$this->denyPrivilege]));

        $mockRoleCustomer = $this->createMock(Security\Policy\Role::class, [], [], $role2ClassName, false);
        $mockRoleCustomer->expects($this->any())->method('getPrivilegesByType')->will(self::returnValue([]));

        $this->mockSecurityContext->expects(self::once())->method('getRoles')->will(self::returnValue([$mockRoleAdministrator, $mockRoleCustomer]));

        self::assertFalse($this->privilegeManager->isGranted(MethodPrivilegeInterface::class, new MethodPrivilegeSubject($this->mockJoinPoint)));
    }

    /**
     * @test
     */
    public function isGrantedGrantsAccessIfAGrantPrivilegeAndNoDenyPrivilegeWasConfigured()
    {
        $role1ClassName = 'role1' . md5(uniqid(mt_rand(), true));
        $role2ClassName = 'role2' . md5(uniqid(mt_rand(), true));

        $mockRoleAdministrator = $this->createMock(Security\Policy\Role::class, [], [], $role1ClassName, false);
        $mockRoleAdministrator->expects($this->any())->method('getPrivilegesByType')->will(self::returnValue([$this->grantPrivilege]));

        $mockRoleCustomer = $this->createMock(Security\Policy\Role::class, [], [], $role2ClassName, false);
        $mockRoleCustomer->expects($this->any())->method('getPrivilegesByType')->will(self::returnValue([]));

        $this->mockSecurityContext->expects(self::once())->method('getRoles')->will(self::returnValue([$mockRoleAdministrator, $mockRoleCustomer]));

        self::assertTrue($this->privilegeManager->isGranted(MethodPrivilegeInterface::class, new MethodPrivilegeSubject($this->mockJoinPoint)));
    }

    /**
     * @test
     */
    public function isPrivilegeTargetGrantedReturnsFalseIfOneVoterReturnsADenyVote()
    {
        $mockRole1 = $this->getMockBuilder(Security\Policy\Role::class)->disableOriginalConstructor()->getMock();
        $mockRole1->expects($this->any())->method('getPrivilegeForTarget')->will(self::returnValue($this->grantPrivilege));
        $mockRole2 = $this->getMockBuilder(Security\Policy\Role::class)->disableOriginalConstructor()->getMock();
        $mockRole2->expects($this->any())->method('getPrivilegeForTarget')->will(self::returnValue($this->abstainPrivilege));
        $mockRole3 = $this->getMockBuilder(Security\Policy\Role::class)->disableOriginalConstructor()->getMock();
        $mockRole3->expects($this->any())->method('getPrivilegeForTarget')->will(self::returnValue($this->denyPrivilege));

        $this->mockSecurityContext->expects($this->any())->method('getRoles')->will(self::returnValue([$mockRole1, $mockRole2, $mockRole3]));

        self::assertFalse($this->privilegeManager->isPrivilegeTargetGranted('somePrivilegeTargetIdentifier'));
    }

    /**
     * @test
     */
    public function isPrivilegeTargetGrantedReturnsFalseIfAllVotersAbstainAndAllowAccessIfAllVotersAbstainIsFalse()
    {
        $mockRole1 = $this->getMockBuilder(Security\Policy\Role::class)->disableOriginalConstructor()->getMock();
        $mockRole1->expects($this->any())->method('getPrivilegeForTarget')->will(self::returnValue($this->abstainPrivilege));
        $mockRole2 = $this->getMockBuilder(Security\Policy\Role::class)->disableOriginalConstructor()->getMock();
        $mockRole2->expects($this->any())->method('getPrivilegeForTarget')->will(self::returnValue($this->abstainPrivilege));
        $mockRole3 = $this->getMockBuilder(Security\Policy\Role::class)->disableOriginalConstructor()->getMock();
        $mockRole3->expects($this->any())->method('getPrivilegeForTarget')->will(self::returnValue($this->abstainPrivilege));

        $this->mockSecurityContext->expects($this->any())->method('getRoles')->will(self::returnValue([$mockRole1, $mockRole2, $mockRole3]));

        self::assertFalse($this->privilegeManager->isPrivilegeTargetGranted('somePrivilegeTargetIdentifier'));
    }

    /**
     * @test
     */
    public function isPrivilegeTargetGrantedPrivilegeReturnsTrueIfAllVotersAbstainAndAllowAccessIfAllVotersAbstainIsTrue()
    {
        $this->inject($this->privilegeManager, 'allowAccessIfAllAbstain', true);

        $mockRole1 = $this->getMockBuilder(Security\Policy\Role::class)->disableOriginalConstructor()->getMock();
        $mockRole1->expects($this->any())->method('getPrivilegeForTarget')->will(self::returnValue($this->abstainPrivilege));
        $mockRole2 = $this->getMockBuilder(Security\Policy\Role::class)->disableOriginalConstructor()->getMock();
        $mockRole2->expects($this->any())->method('getPrivilegeForTarget')->will(self::returnValue($this->abstainPrivilege));
        $mockRole3 = $this->getMockBuilder(Security\Policy\Role::class)->disableOriginalConstructor()->getMock();
        $mockRole3->expects($this->any())->method('getPrivilegeForTarget')->will(self::returnValue($this->abstainPrivilege));

        $this->mockSecurityContext->expects($this->any())->method('getRoles')->will(self::returnValue([$mockRole1, $mockRole2, $mockRole3]));

        self::assertTrue($this->privilegeManager->isPrivilegeTargetGranted('somePrivilegeTargetIdentifier'));
    }

    /**
     * @test
     */
    public function isPrivilegeTargetGrantedReturnsTrueIfThereIsNoDenyVoteAndOneGrantVote()
    {
        $mockRole1 = $this->getMockBuilder(Security\Policy\Role::class)->disableOriginalConstructor()->getMock();
        $mockRole1->expects($this->any())->method('getPrivilegeForTarget')->will(self::returnValue($this->abstainPrivilege));
        $mockRole2 = $this->getMockBuilder(Security\Policy\Role::class)->disableOriginalConstructor()->getMock();
        $mockRole2->expects($this->any())->method('getPrivilegeForTarget')->will(self::returnValue($this->grantPrivilege));
        $mockRole3 = $this->getMockBuilder(Security\Policy\Role::class)->disableOriginalConstructor()->getMock();
        $mockRole3->expects($this->any())->method('getPrivilegeForTarget')->will(self::returnValue($this->abstainPrivilege));

        $this->mockSecurityContext->expects($this->any())->method('getRoles')->will(self::returnValue([$mockRole1, $mockRole2, $mockRole3]));

        self::assertTrue($this->privilegeManager->isPrivilegeTargetGranted('somePrivilegeTargetIdentifier'));
    }
}
