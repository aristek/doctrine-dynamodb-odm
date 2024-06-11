### v0.1.11
##### Fix:
- Added implement `Symfony\Contracts\Service\ResetInterface` for clear [UnitOfWork](src/ODM/UnitOfWork.php) between http requests.

### v0.1.10
##### Fix:
- Fixed clear [UnitOfWork](src/ODM/UnitOfWork.php) on [ManagerRegistry::resetManager](src/ODM/ManagerRegistry.php).

### v0.1.9
##### Fix:
- Fixed library naming;
- Initialization [ManagerRegistry](src/ODM/ManagerRegistry.php) as lazy.

### v0.1.8
##### Fix:
- Fixed functionality with custom partition key names.

### v0.1.7
##### Features:
- Added logging for Dynamodb query to QueryBuilder.

### v0.1.6
##### Fix:
- Fixed global secondary index.

### v0.1.5
##### Features:
- Added repository as service and document listener.

### v0.1.4
##### Features:
- Added logger.

### v0.1.3
##### Fix:
- Added clearing Dynamodb query expressions.

### v0.1.2
##### Features:
- Added [FixturesTrait.php](src/ODM/Test/Helper/FixturesTrait.php).

### v0.1.1
##### Features:
- Rename [Index.php](src/ODM/Id/PrimaryKey.php) to [PrimaryKey.php](src/ODM/Id/PrimaryKey.php).

### v0.1.0
##### Features:
- Base implementation.
