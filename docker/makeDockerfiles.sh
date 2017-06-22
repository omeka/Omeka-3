#!/bin/bash

# Get to the parent path.
parent_path=$( cd "$(dirname "${BASH_SOURCE[0]}")" ; pwd -P )
cd "$parent_path"

### TO RUN, REMEMBER TO LOGIN.
# docker login -e $DOCKER_EMAIL -u $DOCKER_USER -p $DOCKER_PASS

DOCKER_USER='digirati'
PACKAGE_NAME='omeka-s'
PHP_VERSIONS=(7.0.18 7.1 5.6.30);
# WEB_SERVERS=(fpm apache);
WEB_SERVERS=(fpm);

function buildDockerFile {
  echo -e "\033[00;32m========================================================";
  echo -e "Building Docker image...";
  echo -e "========================================================\033[0m";

  cd ../;
  echo -e " ===> Building Dockerfile: ${1} \n";
  DOCKERFILE="docker/build/${1}";
  docker build --file=${DOCKERFILE} -t ${PACKAGE_NAME}:${2} .
  
  if [[ "$(docker images -q ${PACKAGE_NAME}:${2} 2> /dev/null)" == "" ]]; then
    echo -e "\033[00;32m ===> Image was NOT built, failing the build";
    exit 1
  fi
  
  docker tag ${PACKAGE_NAME}:${2} ${DOCKER_USER}/${PACKAGE_NAME}:${2}

  docker exec -i mysql mysql -uroot  <<< "create database IF NOT EXISTS omeka_test;"

  docker images
  
  echo -e "\033[00;32m ===> Spinning up a container running ${2} and attempting to run unit tests \033[0m\n";
  docker run -i -t --link mysql:mysql ${PACKAGE_NAME}:${2} /bin/sh -c "sed -i 's/^host.*/host = "mysql"/' application/test/config/database.ini && sed -i 's/^user.*/user = "root"/' application/test/config/database.ini && sed -i 's/^dbname.*/dbname = "omeka_test"/' application/test/config/database.ini &&./node_modules/gulp/bin/gulp.js test:php"
  
  echo -e "\033[00;32m ===> Dropping previous database\033[0m\n";
  docker exec -i mysql mysql -uroot  <<< "drop database omeka_test;"
  
  if [ $? -ne 0 ]; then
   echo -e "\033[00;31m ERROR: Unit tests didn't pass, we won't we pushing this image\033[0m\n";
  else
    if [[ "$TRAVIS_BRANCH" = "develop" ]] && [[ "$TRAVIS_PULL_REQUEST" = "false" ]]; then
      echo -e "\033[00;32m ===> Pushing to Dockerhub\033[0m\n";
      docker push ${DOCKER_USER}/${PACKAGE_NAME}:${2}
      echo -e "\033[00;32m====================S=U=C=C=E=S=S=======================\033[0m\n";
    else
      echo -e "\033[00;31m Unit tests passed but we're not pushing this as its a PR or a feature branch\033[0m\n";
    fi
  fi 
  # This needs to always happen, see line 21
  cd -;
}

echo -e "\033[00;32m ===> Creating database container for running tests\033[0m\n";

docker run --name mysql -d -p 3306:3306 -e "MYSQL_ALLOW_EMPTY_PASSWORD=yes"  mysql:5.7

# Wait for database to be ready
until nc -z -v -w30 localhost 3306
do
   echo -e "\033[00;33m ===> Waiting for database connection...\033[0m\n";
   # wait for 5 seconds before check again
   sleep 5
done


for php in "${PHP_VERSIONS[@]}"
do
  :
  for server in "${WEB_SERVERS[@]}"
  do
    :

    DOCKER_TAG="${php}-${server}"
    BASE_TEMPLATE="./Dockerfile"
    PHP_TEMPLATE="./configs/php/${php}.sed"
    OUTFILE="${PACKAGE_NAME}-${php}-${server}.Dockerfile"
    sed -f ${PHP_TEMPLATE} ${BASE_TEMPLATE} > ./build/${OUTFILE}

    WEB_TEMPLATE="./configs/server/${server}.sed"
    sed -f ${WEB_TEMPLATE} -i.bak ./build/${OUTFILE}

    rm ./build/${OUTFILE}.bak

    buildDockerFile ${OUTFILE} ${DOCKER_TAG}

  done
done

if [[ "$TRAVIS_BRANCH" = "develop" ]] && [[ "$TRAVIS_PULL_REQUEST" = "false" ]]; then
  echo -e "\033[00;32m========================================================";
  echo -e "Pushing PHP 7.1 FPM with the tag latest";
  echo -e "========================================================\033[0m";

  docker tag omeka-s:7.1-fpm ${DOCKER_USER}/${PACKAGE_NAME}:latest
  docker push ${DOCKER_USER}/${PACKAGE_NAME}:latest
  
  if [ $? -eq 0 ]; then
    # Trigger a traverse into NLW build
    body='{
    "request": {
    "branch":"master"
    }}'

    echo -e "\033[00;32m ===> Pushing this change downstream to the NLW omeka build \033[0m\n";

    curl -s -X POST -H "Content-Type: application/json" -H "Accept: application/json" -H "Travis-API-Version: 3" -H "Authorization: token ${TRAVIS_ACCESS_TOKEN}" -d "$body" https://api.travis-ci.com/repo/digirati-co-uk%2Fnlw-omeka-build/requests
    
    echo -e "\033[00;32m ===> Pushing this change downstream to the RS omeka build \033[0m\n";

    curl -s -X POST -H "Content-Type: application/json" -H "Accept: application/json" -H "Travis-API-Version: 3" -H "Authorization: token ${TRAVIS_ACCESS_TOKEN}" -d "$body" https://api.travis-ci.com/repo/digirati-co-uk%2Frs-omeka-build/requests
    
    echo -e "\033[00;32m ===> Pushing this change downstream to the Digirati omeka dev build \033[0m\n";

    curl -s -X POST -H "Content-Type: application/json" -H "Accept: application/json" -H "Travis-API-Version: 3" -H "Authorization: token ${TRAVIS_ACCESS_TOKEN}" -d "$body" https://api.travis-ci.com/repo/digirati-co-uk%2Fomeka-s-dev/requests


    echo -e "\033[00;32m====================S=U=C=C=E=S=S=======================\033[0m\n";
  fi
  
fi

