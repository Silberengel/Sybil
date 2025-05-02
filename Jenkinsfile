pipeline {
    agent any
    
    parameters {
        booleanParam(name: 'TEST_MODE', defaultValue: false, description: 'Run in test mode to publish Office of Readings')
    }
    
    triggers {
        // Run at 6 AM, 12 PM, and 10 PM Berlin time
        cron('0 6,12,22 * * *')
    }
    
    environment {
        TZ = 'Europe/Berlin'
    }
    
    stages {
        stage('Sanity Check') {
            steps {
                echo 'Jenkinsfile is being executed!'
            }
        }
        stage('Debug Workspace') {
            steps {
                sh 'pwd'
                sh 'ls -l'
                sh 'ls -l scripts'
            }
        }
        stage('Setup') {
            steps {
                // Make the script executable
                sh 'chmod +x scripts/divine_office_bot.sh'
            }
        }
        
        stage('Run Divine Office Bot') {
            steps {
                // Run the bot script with test parameter if enabled
                script {
                    if (params.TEST_MODE) {
                        sh './scripts/divine_office_bot.sh --test'
                    } else {
                        sh './scripts/divine_office_bot.sh'
                    }
                }
            }
        }
    }
    
    post {
        always {
            // Clean up workspace
            cleanWs()
        }
        success {
            echo 'Divine Office bot completed successfully'
        }
        failure {
            echo 'Divine Office bot failed'
        }
    }
} 