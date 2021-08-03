const CwaAdapter = require('./cwa_adapter.js')

const cwaAdapter = new CwaAdapter({
  baseURL: '[DCC Server URL]', // Eg: 'https://quicktest-result-cff4f7147260.coronawarn.app'
  // If the .cer file throws an invalid certificate error, try converting the .cer that was sent to you to .crt with openssl:
  // openssl x509 -inform PEM -in my-cwa-certificate.cer -out my-cwa-certificate.crt
  certPath: '[Path to .crt or .cer file]', // Eg: './../CSR_generator/cwa.crt'
  keyPath: '[Path to .key file]', // Eg: './../CSR_generator/cwa.key'
  // you can pass the passphrase to the key file here, or alternatively you may remove the passphrase from your key file with openssl, in which case you will not need to use the passphrase:
  // openssl rsa -in my-cwa-certificate.key -out my-cwa-certificate.key
  passphrase: '[Passphrase]'
})

const exampleTestData = {
  fn: 'Erika',
  ln: 'Mustermann',
  dob: '1990-12-23',
  timestamp: 1618386548
}
const exampleResult = 6 // 6: negative, 7: positive, 8: invalid
const exampleTime = 1618389548 // optional result time

// you should save the hash and testid (if you didn't use your own testid)
const { hash, url, testid } = cwaAdapter.prepareCwaData(exampleTestData)
cwaAdapter.sendTestResult({ hash, result: exampleResult, sc: exampleTime })
  .then((status) => {
    if (status === 204) {
      console.info('Test result was successfuly sent.', { ...exampleTestData, hash, url, testid })
      return
    }
    console.warn('Test result failed', status)
  })
  .catch(console.error)
