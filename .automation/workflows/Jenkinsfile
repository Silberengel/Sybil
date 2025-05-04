pipeline {
    agent any
    
    parameters {
        booleanParam(name: 'TEST_MODE', defaultValue: false, description: 'Run in test mode')
        choice(name: 'SCRIPT_TO_RUN', choices: ['both', 'publication', 'notes'], description: 'Which script(s) to run')
    }
    
    triggers {
        // Run publication at 2 AM Berlin time
        cron('0 2 * * *')
        // Run notes at 6 AM, 12 PM, and 10 PM Berlin time
        cron('0 6,12,22 * * *')
    }
    
    environment {
        TZ = 'Europe/Berlin'
    }
    
    stages {
        stage('Sanity Check') {
            steps {
                echo 'Jenkinsfile is being executed!'
                echo "Current timezone: ${TZ}"
                echo "Current time: ${new Date().format('yyyy-MM-dd HH:mm:ss')}"
                echo "Test mode: ${params.TEST_MODE}"
                echo "Script to run: ${params.SCRIPT_TO_RUN}"
            }
        }
        stage('Debug Workspace') {
            steps {
                echo '=== Current Directory ==='
                sh 'pwd'
                echo '=== Directory Contents ==='
                sh 'ls -la'
                echo '=== Scripts Directory ==='
                sh 'ls -la scripts/'
                echo '=== Liturgy Directory ==='
                sh 'ls -la src/testdata/Publications/Liturgy || true'
                echo '=== Bin Directory ==='
                sh 'ls -la bin/ || true'
                echo '=== PHP Version ==='
                sh 'php -v'
                echo '=== Composer Version ==='
                sh 'composer --version'
            }
        }
        stage('Install Composer Dependencies') {
            steps {
                echo 'Installing Composer dependencies...'
                sh 'composer install --no-interaction --prefer-dist --verbose'
            }
        }
        stage('Setup') {
            steps {
                echo 'Making scripts executable...'
                sh '''
                    chmod +x scripts/publish_divine_office.sh scripts/publish_office_notes.sh
                    echo "Script permissions:"
                    ls -l scripts/publish_divine_office.sh scripts/publish_office_notes.sh
                '''
            }
        }
        
        stage('Run Divine Office Publication') {
            when {
                expression { 
                    params.SCRIPT_TO_RUN == 'both' || params.SCRIPT_TO_RUN == 'publication'
                }
            }
            steps {
                echo '=== Running Divine Office Publication ==='
                script {
                    try {
                        if (params.TEST_MODE) {
                            sh './scripts/publish_divine_office.sh --test'
                        } else {
                            sh './scripts/publish_divine_office.sh'
                        }
                    } catch (Exception e) {
                        echo "ERROR: Publication script failed with error: ${e.message}"
                        echo "Stack trace: ${e.stackTrace}"
                        throw e
                    }
                }
            }
        }
        
        stage('Run Office Notes') {
            when {
                expression { 
                    params.SCRIPT_TO_RUN == 'both' || params.SCRIPT_TO_RUN == 'notes'
                }
            }
            steps {
                echo '=== Running Office Notes ==='
                script {
                    try {
                        if (params.TEST_MODE) {
                            sh './scripts/publish_office_notes.sh --test'
                        } else {
                            sh './scripts/publish_office_notes.sh'
                        }
                    } catch (Exception e) {
                        echo "ERROR: Notes script failed with error: ${e.message}"
                        echo "Stack trace: ${e.stackTrace}"
                        throw e
                    }
                }
            }
        }
    }
    
    post {
        always {
            echo '=== Build Environment ==='
            echo "Build number: ${env.BUILD_NUMBER}"
            echo "Build ID: ${env.BUILD_ID}"
            echo "Build URL: ${env.BUILD_URL}"
            echo "Job name: ${env.JOB_NAME}"
            echo "Workspace: ${env.WORKSPACE}"
            
            // Clean up workspace
            cleanWs()
        }
        success {
            echo '=== Build Success ==='
            echo 'Divine Office scripts completed successfully'
        }
        failure {
            echo '=== Build Failure ==='
            echo 'Divine Office scripts failed'
            echo 'Check the console output above for detailed error messages'
        }
    }
} 