pipeline {
    agent any
    
    triggers {
        // Run at 6 AM, 12 PM, and 10 PM Berlin time
        cron('0 6,12,22 * * *')
    }
    
    environment {
        TZ = 'Europe/Berlin'
    }
    
    stages {
        stage('Setup') {
            steps {
                // Make the script executable
                sh 'chmod +x scripts/divine_office_bot.sh'
            }
        }
        
        stage('Run Divine Office Bot') {
            steps {
                // Run the bot script
                sh './scripts/divine_office_bot.sh'
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