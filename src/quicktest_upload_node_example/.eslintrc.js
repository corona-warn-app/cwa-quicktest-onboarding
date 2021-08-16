module.exports = {
  env: {
    commonjs: true,
    es2020: true,
    node: true
  },
  extends: ['eslint:recommended', 'standard'],
  parserOptions: {
    ecmaVersion: 11
  },
  globals: {
    Parse: true,
    consola: true,
    BASE_DIR: true,
    DEVELOPMENT: true,
    moment: true
  },
  // add your custom rules here
  rules: {
    camelcase: 'off'
  }
}
